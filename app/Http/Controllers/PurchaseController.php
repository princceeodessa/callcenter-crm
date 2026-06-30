<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseStage;
use App\Services\Warehouse\WarehouseService;
use App\Support\Users\AssignmentScope;
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
