<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\PurchaseStage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerDashboardDeliveryKpiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_dashboard_shows_money_and_units_currently_in_delivery(): void
    {
        $accountId = User::where('role', 'sneaker_head')->firstOrFail()->account_id;
        $owner = User::create([
            'account_id' => $accountId,
            'name' => 'Test Owner',
            'email' => 'owner_kpi_test_'.uniqid(),
            'password' => Hash::make('password'),
            'role' => 'sneaker_owner',
            'is_active' => true,
        ]);

        $inTransit = PurchaseStage::where('account_id', $accountId)->where('name', 'В пути')->firstOrFail();
        $received = PurchaseStage::where('account_id', $accountId)->where('name', 'Получено / На складе')->firstOrFail();

        // В пути, не на складе — должно попасть в "В доставке".
        Purchase::create([
            'account_id' => $accountId, 'purchase_stage_id' => $inTransit->id,
            'title' => 'KPI test A', 'brand' => 'KPITEST', 'model' => 'A', 'size' => '42',
            'quantity' => 3, 'cost' => 2000, 'currency' => 'RUB',
        ]);
        Purchase::create([
            'account_id' => $accountId, 'purchase_stage_id' => $inTransit->id,
            'title' => 'KPI test B', 'brand' => 'KPITEST', 'model' => 'B', 'size' => '43',
            'quantity' => 1, 'cost' => 500, 'currency' => 'RUB',
        ]);
        // Уже на складе (stocked_at проставлен) — НЕ должно попасть в "В доставке".
        $stocked = Purchase::create([
            'account_id' => $accountId, 'purchase_stage_id' => $received->id,
            'title' => 'KPI test C (already stocked)', 'brand' => 'KPITEST', 'model' => 'C', 'size' => '44',
            'quantity' => 10, 'cost' => 9999, 'currency' => 'RUB',
        ]);
        $stocked->forceFill(['stocked_at' => now(), 'stocked_quantity' => 10])->save();
        // Закрытая (архив), не на складе — НЕ должна учитываться (списана как отмена).
        $closed = Purchase::create([
            'account_id' => $accountId, 'purchase_stage_id' => $inTransit->id,
            'title' => 'KPI test D (closed)', 'brand' => 'KPITEST', 'model' => 'D', 'size' => '45',
            'quantity' => 7, 'cost' => 7777, 'currency' => 'RUB',
        ]);
        $closed->forceFill(['closed_at' => now()])->save();

        $response = $this->actingAs($owner)->get(route('owner.dashboard'));
        $response->assertOk();
        $response->assertViewHas('inDeliveryUnits', 4); // 3 + 1, excludes stocked and closed
        $response->assertViewHas('inDeliveryValue', 6500.0); // 3*2000 + 1*500
        $response->assertSee('В доставке');
    }
}
