<?php

namespace App\Http\Controllers;

use App\Models\GiftCertificate;
use App\Models\GiftCertificateSale;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Sales\GiftCertificates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * «Сертификаты» кроссовок (10.11.2026): остаток по номиналам, продажа сертификата (деньги в кассу, но не выручка),
 * журнал продаж, отмена ошибочной. Руководитель правит остаток и номиналы.
 * С 26.11.2026 — обналичивание: клиент сдаёт сертификат и получает его номинал деньгами (из кассы, не выручка и не
 * расход); сертификат возвращается в остаток, руководителю — уведомление.
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
        $seesCost = \App\Support\Ui\Sneakers::seesCost($user);   // маржа пар, оплаченных сертификатами, — руководителю

        return view('certificates.index', compact('nominals', 'sales', 'month', 'today', 'isHead', 'sold', 'seesCost'));
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

    /**
     * Обналичить: клиент сдаёт сертификат(ы) и получает номинал деньгами. Больше, чем сейчас на руках у клиентов
     * (продано − потрачено − обналичено), обналичить нельзя. Сертификат возвращается в остаток.
     */
    public function cashout(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'nominal' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1', 'max:100'],
            'payment' => ['required', Rule::in(array_keys(GiftCertificates::moneyMethods()))],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:32'],
        ], ['payment.required' => 'Выберите, как выдали деньги клиенту.']);

        $row = DB::transaction(function () use ($user, $data) {
            $cert = GiftCertificate::where('account_id', $user->account_id)->where('nominal', (int) $data['nominal'])->lockForUpdate()->first();
            abort_unless($cert, 422, 'Такого номинала нет.');
            $amount = $cert->nominal * (int) $data['qty'];
            if ($amount > GiftCertificates::outstanding($user->account_id) + 0.009) {
                return null;
            }
            $cert->increment('stock', (int) $data['qty']);

            return GiftCertificateSale::create([
                'account_id' => $user->account_id,
                'kind' => GiftCertificateSale::KIND_CASHOUT,
                'nominal' => $cert->nominal,
                'qty' => (int) $data['qty'],
                'amount' => $amount,
                'payment_method' => $data['payment'],
                'user_id' => $user->id,
                'client_name' => trim((string) ($data['client_name'] ?? '')) ?: null,
                'client_phone' => trim((string) ($data['client_phone'] ?? '')) ?: null,
                'sold_at' => now(),
            ]);
        });
        if (! $row) {
            return back()->withInput()->withErrors(['cashout' => 'Столько сертификатов клиентам не продано (на руках у клиентов '
                .number_format(max(0, GiftCertificates::outstanding($user->account_id)), 0, ',', ' ').' ₽) — проверьте номинал и количество.']);
        }
        $this->notifyCashout($row, $user);

        return redirect()->route('certificates.index', ['sold' => $row->id]);
    }

    /**
     * Ошибочная строка журнала: продажа — сертификаты возвращаются в остаток, обналичивание — снова уходят из остатка.
     * Продавец — свою за сегодня, руководитель — любую.
     */
    public function cancel(GiftCertificateSale $sale)
    {
        $user = Auth::user();
        abort_unless($sale->account_id === $user->account_id, 404);
        abort_unless($user->role === 'sneaker_head' || ((int) $sale->user_id === (int) $user->id && $sale->sold_at->isToday()), 403);
        if ($sale->cancelled_at) {
            return back()->with('status', $sale->isCashout() ? 'Это обналичивание уже отменено.' : 'Эта продажа уже отменена.');
        }
        DB::transaction(function () use ($sale, $user) {
            $sale->forceFill(['cancelled_at' => now(), 'cancelled_by' => $user->id])->save();
            $cert = GiftCertificate::where('account_id', $sale->account_id)->where('nominal', $sale->nominal)->lockForUpdate()->first();
            if ($cert) {
                $cert->stock = $sale->isCashout() ? max(0, $cert->stock - $sale->qty) : $cert->stock + $sale->qty;
                $cert->save();
            }
            if ($sale->isCashout()) {
                UserNotification::where('account_id', $sale->account_id)->where('type', 'certificate_cashout')->where('source_id', $sale->id)->delete();
            }
        });

        return back()->with('status', $sale->isCashout()
            ? 'Обналичивание отменено, '.$sale->qty.' шт снова у клиента.'
            : 'Продажа сертификата отменена, '.$sale->qty.' шт вернулись в остаток.');
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

    /** Руководителю — уведомление (колокольчик): деньги из кассы за сданный сертификат */
    private function notifyCashout(GiftCertificateSale $row, User $author): void
    {
        $heads = User::where('account_id', $row->account_id)->where('role', 'sneaker_head')->where('is_active', true)
            ->where('id', '!=', $author->id)->pluck('id');
        $client = trim(($row->client_name ?? '').' '.($row->client_phone ?? ''));
        $body = $author->name.': сертификат '.number_format($row->nominal, 0, ',', ' ').' ₽'.($row->qty > 1 ? ' × '.$row->qty : '')
            .' — выдано '.GiftCertificates::changeWord($row->payment_method).' '.number_format((float) $row->amount, 0, ',', ' ').' ₽'
            .($client !== '' ? ' · '.$client : '');
        foreach ($heads as $uid) {
            UserNotification::create([
                'account_id' => $row->account_id, 'user_id' => $uid, 'type' => 'certificate_cashout', 'title' => 'Обналичили сертификат',
                'body' => $body, 'source_type' => 'gift_certificate_sale', 'source_id' => $row->id,
                'payload' => ['context_url' => route('certificates.index')], 'is_read' => false,
            ]);
        }
    }
}
