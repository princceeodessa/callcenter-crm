<?php

namespace App\Http\Controllers;

use App\Models\GiftCertificate;
use App\Models\GiftCertificateSale;
use App\Support\Sales\GiftCertificates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * «Сертификаты» кроссовок (10.11.2026): остаток по номиналам, продажа сертификата (деньги в кассу, но не выручка),
 * журнал продаж, отмена ошибочной. Руководитель правит остаток и номиналы.
 */
class GiftCertificateController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accId = $user->account_id;
        $nominals = GiftCertificates::nominals($accId);
        $sales = GiftCertificateSale::where('account_id', $accId)->with(['user:id,name'])
            ->where('sold_at', '>=', now()->subDays(60)->startOfDay())->orderByDesc('sold_at')->orderByDesc('id')->limit(200)->get();
        $month = GiftCertificates::summary($accId, now()->startOfMonth(), now()->endOfDay());
        $today = GiftCertificates::summary($accId, now()->startOfDay(), now()->endOfDay());
        $isHead = $user->role === 'sneaker_head';
        $sold = $request->filled('sold') ? GiftCertificateSale::where('account_id', $accId)->whereKey((int) $request->query('sold'))->first() : null;

        return view('certificates.index', compact('nominals', 'sales', 'month', 'today', 'isHead', 'sold'));
    }

    public function sell(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'nominal' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1', 'max:100'],
            'payment' => ['required', Rule::in(array_keys(GiftCertificates::moneyMethods()))],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:32'],
        ], ['payment.required' => 'Выберите, как клиент заплатил за сертификат.']);

        $sale = DB::transaction(function () use ($user, $data) {
            $cert = GiftCertificate::where('account_id', $user->account_id)->where('nominal', (int) $data['nominal'])->lockForUpdate()->first();
            abort_unless($cert, 422, 'Такого номинала нет.');
            if ($cert->stock < (int) $data['qty']) {
                return null;
            }
            $cert->decrement('stock', (int) $data['qty']);

            return GiftCertificateSale::create([
                'account_id' => $user->account_id,
                'nominal' => $cert->nominal,
                'qty' => (int) $data['qty'],
                'amount' => $cert->nominal * (int) $data['qty'],
                'payment_method' => $data['payment'],
                'user_id' => $user->id,
                'client_name' => trim((string) ($data['client_name'] ?? '')) ?: null,
                'client_phone' => trim((string) ($data['client_phone'] ?? '')) ?: null,
                'sold_at' => now(),
            ]);
        });
        if (! $sale) {
            return back()->withInput()->withErrors(['qty' => 'Столько сертификатов этого номинала нет — проверьте остаток или скажите руководителю.']);
        }

        return redirect()->route('certificates.index', ['sold' => $sale->id]);
    }

    /** Ошибочная продажа: сертификаты возвращаются в остаток. Продавец — свою за сегодня, руководитель — любую. */
    public function cancel(GiftCertificateSale $sale)
    {
        $user = Auth::user();
        abort_unless($sale->account_id === $user->account_id, 404);
        abort_unless($user->role === 'sneaker_head' || ((int) $sale->user_id === (int) $user->id && $sale->sold_at->isToday()), 403);
        if ($sale->cancelled_at) {
            return back()->with('status', 'Эта продажа уже отменена.');
        }
        DB::transaction(function () use ($sale, $user) {
            $sale->forceFill(['cancelled_at' => now(), 'cancelled_by' => $user->id])->save();
            GiftCertificate::where('account_id', $sale->account_id)->where('nominal', $sale->nominal)->increment('stock', $sale->qty);
        });

        return back()->with('status', 'Продажа сертификата отменена, '.$sale->qty.' шт вернулись в остаток.');
    }

    /** Руководитель: остаток номинала (пришла новая пачка, пересчитали) или новый номинал. */
    public function stock(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->role === 'sneaker_head', 403);
        $data = $request->validate([
            'nominal' => ['required', 'integer', 'min:100', 'max:1000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        $cert = GiftCertificate::firstOrNew(['account_id' => $user->account_id, 'nominal' => (int) $data['nominal']]);
        $was = $cert->exists ? $cert->stock : null;
        $cert->stock = (int) $data['stock'];
        $cert->save();

        return back()->with('status', 'Сертификат '.number_format($cert->nominal, 0, ',', ' ').' ₽: остаток '.$cert->stock.' шт'.($was !== null ? ' (было '.$was.')' : ' — новый номинал').'.');
    }
}
