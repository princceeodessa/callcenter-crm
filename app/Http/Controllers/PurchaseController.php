<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseStage;
use App\Services\Warehouse\WarehouseService;
use App\Support\Users\AssignmentScope;
use App\Support\Warehouse\ArticleIdentity;
use App\Support\Warehouse\DeliverySheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseController extends Controller
{
    /** Канбан закупок (drag&drop по стадиям). */
    public function kanban(Request $request)
    {
        $user = Auth::user();
        $q = trim($request->string('q')->toString());

        $stages = PurchaseStage::query()
            ->where('account_id', $user->account_id)
            ->orderBy('sort')
            ->get();

        $purchasesByStage = Purchase::query()
            ->with('responsible')
            ->where('account_id', $user->account_id)
            ->whereNull('closed_at')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('title', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('article', 'like', "%{$q}%")
                        ->orWhere('supplier', 'like', "%{$q}%");
                });
            })
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get()
            ->groupBy('purchase_stage_id');

        return view('purchases.kanban', compact('stages', 'purchasesByStage', 'q'));
    }

    public function create()
    {
        $user = Auth::user();

        $stages = PurchaseStage::query()
            ->where('account_id', $user->account_id)
            ->orderBy('sort')
            ->get();

        $users = AssignmentScope::query($user)->orderBy('name')->get();

        return view('purchases.create', compact('stages', 'users'));
    }

    public function store(Request $request, WarehouseService $warehouse)
    {
        $user = Auth::user();
        $data = $this->validateData($request);

        $stage = PurchaseStage::query()
            ->where('account_id', $user->account_id)
            ->findOrFail($data['purchase_stage_id']);

        $responsibleId = $this->resolveResponsibleId($user, $data['responsible_user_id'] ?? null);

        $purchase = Purchase::create([
            'account_id' => $user->account_id,
            'purchase_stage_id' => $stage->id,
            'title' => $data['title'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'size' => $data['size'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'supplier' => $data['supplier'] ?? null,
            'cost' => $data['cost'] ?? null,
            'currency' => 'RUB',
            'expected_sale_price' => $data['expected_sale_price'] ?? null,
            'responsible_user_id' => $responsibleId,
            'article' => $data['article'] ?? null,
            'is_white' => $request->boolean('is_white'),
            'notes' => $data['notes'] ?? null,
        ]);

        $warehouse->syncPurchaseStock($purchase);

        return redirect()->route('purchases.show', $purchase);
    }

    public function show(Purchase $purchase)
    {
        $user = Auth::user();
        abort_unless($purchase->account_id === $user->account_id, 403);

        $purchase->load('responsible', 'stage');

        $stages = PurchaseStage::query()
            ->where('account_id', $user->account_id)
            ->orderBy('sort')
            ->get();

        $users = AssignmentScope::query($user)->orderBy('name')->get();

        return view('purchases.show', compact('purchase', 'stages', 'users'));
    }

    public function update(Request $request, Purchase $purchase, WarehouseService $warehouse)
    {
        $user = Auth::user();
        abort_unless($purchase->account_id === $user->account_id, 403);

        $data = $this->validateData($request);

        $stage = PurchaseStage::query()
            ->where('account_id', $user->account_id)
            ->findOrFail($data['purchase_stage_id']);

        $purchase->fill([
            'purchase_stage_id' => $stage->id,
            'title' => $data['title'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'size' => $data['size'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'supplier' => $data['supplier'] ?? null,
            'cost' => $data['cost'] ?? null,
            'expected_sale_price' => $data['expected_sale_price'] ?? null,
            'responsible_user_id' => $this->resolveResponsibleId($user, $data['responsible_user_id'] ?? null),
            'article' => $data['article'] ?? null,
            'is_white' => $request->boolean('is_white'),
            'notes' => $data['notes'] ?? null,
        ]);
        $purchase->save();

        $warehouse->syncPurchaseStock($purchase);

        return redirect()->route('purchases.show', $purchase)->with('status', 'Закупка обновлена.');
    }

    /** Канбан drag&drop: перенос карточки в другую стадию. */
    public function move(Request $request, Purchase $purchase, WarehouseService $warehouse)
    {
        $user = Auth::user();
        if ($purchase->account_id !== $user->account_id) {
            abort(403);
        }

        $data = $request->validate([
            'to_stage_id' => ['required', 'integer', 'exists:purchase_stages,id'],
        ]);

        $to = PurchaseStage::query()
            ->where('account_id', $user->account_id)
            ->find($data['to_stage_id']);

        if (! $to) {
            return response()->json(['ok' => false, 'message' => 'Нет доступа'], 403);
        }

        if ((int) $purchase->purchase_stage_id !== (int) $to->id) {
            $purchase->purchase_stage_id = $to->id;
            $purchase->save();
            $warehouse->syncPurchaseStock($purchase);
        }

        return response()->json([
            'ok' => true,
            'last_moved_by_label' => 'Изменил: '.$user->name,
        ]);
    }

    /** Закрыть (архивировать) закупку — уходит из канбана, остаток на складе сохраняется. */
    public function close(Purchase $purchase)
    {
        $user = Auth::user();
        abort_unless($purchase->account_id === $user->account_id, 403);

        if (! $purchase->closed_at) {
            $purchase->closed_at = now();
            $purchase->save();
        }

        return redirect()->route('purchases.kanban')->with('status', 'Закупка закрыта (архив). Остаток на складе сохранён.');
    }

    /** Форма загрузки таблицы поставки (.xlsx). */
    public function importForm()
    {
        return view('purchases.import', ['imported' => null]);
    }

    /**
     * Загрузить таблицу поставки. Колонки распознаются по заголовкам, см. DeliverySheet.
     * Каждая строка — отдельная карточка закупки в стадии «В пути» (ещё не на складе).
     * Артикул канонизирует бренд+модель так же, как обычный импорт склада — одна
     * расцветка не распадается на разные карточки товара при последующей приёмке.
     */
    public function importRun(Request $request, WarehouseService $warehouse)
    {
        $user = Auth::user();
        $request->validate([
            'xlsx' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $parsed = DeliverySheet::parse($request->file('xlsx')->getRealPath());
        $rows = $parsed['rows'];
        if (empty($rows)) {
            return back()->withErrors(['xlsx' => 'Не нашли ни одной строки с названием и размером. Нужны колонки «Название», «Размер», и желательно «Артикул», «Количество», «Цена с НДС» (или «Сумма»).']);
        }

        $stage = PurchaseStage::where('account_id', $user->account_id)->where('name', 'В пути')->first()
            ?? PurchaseStage::where('account_id', $user->account_id)->where('is_stock_in', false)->orderByDesc('sort')->first()
            ?? PurchaseStage::where('account_id', $user->account_id)->orderBy('sort')->first();

        if (! $stage) {
            return back()->withErrors(['xlsx' => 'Стадии закупок не настроены. Запустите сидер кроссовочного пространства.']);
        }

        $canonicalByArticle = ArticleIdentity::canonicalizeByArticle($user->account_id, $rows);

        $batch = 'Импорт поставки из Excel ('.now()->format('d.m.Y H:i').')';
        $imported = 0;
        $pairs = 0;
        foreach ($rows as $r) {
            [$brand, $model] = ArticleIdentity::resolve($canonicalByArticle, $r);
            $purchase = Purchase::create([
                'account_id' => $user->account_id,
                'purchase_stage_id' => $stage->id,
                'title' => trim($brand.' '.$model.' р.'.$r['size']),
                'brand' => $brand,
                'model' => $model,
                'size' => $r['size'],
                'quantity' => $r['qty'],
                'cost' => $r['cost'],
                'currency' => 'RUB',
                'article' => $r['article'] !== '' ? $r['article'] : null,
                'notes' => $batch,
            ]);
            $warehouse->syncPurchaseStock($purchase);
            // Регистрируем расцветку сразу (не дожидаясь приёмки), чтобы будущие
            // импорты — этот же или обычный складской — узнали артикул и не задвоили карточку.
            ArticleIdentity::ensureProduct($user->account_id, $brand, $model, $r['article']);
            $imported++;
            $pairs += (int) $r['qty'];
        }

        $columns = [];
        foreach ($parsed['mapping'] as $label => $column) {
            $columns[] = $label.' → '.$column;
        }
        $status = 'Загружено позиций: '.$imported.' ('.$pairs.' пар). Распознаны колонки: '.implode(', ', $columns).'.';
        if ($parsed['skipped'] > 0) {
            $status .= ' Пропущено строк без названия/размера (итоги, примечания): '.$parsed['skipped'].'.';
        }

        return redirect()->route('purchases.inTransit')->with('status', $status);
    }

    /**
     * «В пути» — всё, что закуплено, но ещё не заведено на склад (stocked_at пуст).
     * Отсюда позиции принимаются на склад: выбранные галочками или все сразу.
     */
    public function inTransit(Request $request)
    {
        $user = Auth::user();
        $q = trim($request->string('q')->toString());

        $purchases = Purchase::query()
            ->with('stage')
            ->where('account_id', $user->account_id)
            ->whereNull('closed_at')
            ->whereNull('stocked_at')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('title', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('article', 'like', "%{$q}%")
                        ->orWhere('size', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->get();

        $totalPairs = (int) $purchases->sum('quantity');
        $totalCost = (float) $purchases->sum(fn (Purchase $p) => (int) $p->quantity * (float) ($p->cost ?? 0));
        $avgPairCost = $totalPairs > 0 ? $totalCost / $totalPairs : 0.0;
        $articlesCount = $purchases->pluck('article')->filter()->unique()->count();
        $brandsCount = $purchases->pluck('brand')->filter()->unique()->count();

        // Общий итог — чтобы при поиске было видно, что показана лишь часть поставки.
        $grandTotalPairs = $q === '' ? $totalPairs : (int) Purchase::where('account_id', $user->account_id)
            ->whereNull('closed_at')->whereNull('stocked_at')->sum('quantity');

        return view('purchases.in_transit', compact(
            'purchases', 'q', 'totalPairs', 'totalCost',
            'avgPairCost', 'articlesCount', 'brandsCount', 'grandTotalPairs'
        ));
    }

    /**
     * Принять на склад: выбранные позиции (purchase_ids) либо всё, что в пути (scope=all).
     * Стадия «Получено / На складе» помечена is_stock_in, поэтому остаток заводит
     * штатный WarehouseService::syncPurchaseStock — как при обычном перетаскивании карточки.
     */
    public function receiveBatch(Request $request, WarehouseService $warehouse)
    {
        $user = Auth::user();
        $data = $request->validate([
            'scope' => ['nullable', 'in:all'],
            'purchase_ids' => ['required_without:scope', 'array', 'min:1'],
            'purchase_ids.*' => ['integer'],
        ]);

        $stage = PurchaseStage::where('account_id', $user->account_id)->where('name', 'Получено / На складе')->first()
            ?? PurchaseStage::where('account_id', $user->account_id)->where('is_stock_in', true)->orderBy('sort')->first();

        if (! $stage) {
            return back()->withErrors(['purchase_ids' => 'Стадия «Получено / На складе» не настроена.']);
        }

        $purchases = Purchase::where('account_id', $user->account_id)
            ->whereNull('closed_at')
            // Уже заведённые на склад не трогаем: повторная приёмка задвоила бы остаток.
            ->whereNull('stocked_at')
            ->when(($data['scope'] ?? null) !== 'all', fn ($query) => $query->whereIn('id', $data['purchase_ids'] ?? []))
            ->get();

        $pairs = 0;
        foreach ($purchases as $purchase) {
            if ((int) $purchase->purchase_stage_id !== (int) $stage->id) {
                $purchase->purchase_stage_id = $stage->id;
                $purchase->save();
            }
            $warehouse->syncPurchaseStock($purchase);
            $pairs += (int) $purchase->quantity;
        }

        return redirect()->route('purchases.inTransit')
            ->with('status', 'Принято на склад: '.$purchases->count().' поз. ('.$pairs.' пар).');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'expected_sale_price' => ['nullable', 'numeric', 'min:0'],
            'responsible_user_id' => ['nullable', 'integer'],
            'article' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'purchase_stage_id' => ['required', 'integer', 'exists:purchase_stages,id'],
        ]);
    }

    /** Ответственный должен быть в зоне видимости пользователя и его аккаунте. */
    private function resolveResponsibleId($user, $responsibleId): ?int
    {
        $responsibleId = (int) $responsibleId;
        if ($responsibleId <= 0) {
            return null;
        }

        return AssignmentScope::query($user)->whereKey($responsibleId)->value('id');
    }
}
