<?php

namespace Tests\Feature;

use App\Models\ShopExpense;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * «Расходы» кроссовок (24.11.2026): продавец вносит трату и видит только свои; руководитель и владелец видят все,
 * отмечают «вернули» по тратам своими деньгами; в сводке владельца чистыми = прибыль с продаж − расходы.
 */
class ShopExpensesTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;
    private User $op;
    private int $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->acc = (int) $this->head->account_id;
        $this->op = User::where('role', 'sneaker_operator')->where('account_id', $this->acc)->firstOrFail();
        Storage::fake('local');
    }

    private function owner(): User
    {
        return User::where('role', 'sneaker_owner')->where('account_id', $this->acc)->first()
            ?? User::create(['account_id' => $this->acc, 'name' => 'Владелец (тест)', 'email' => 'own'.uniqid().'@t.local', 'password' => bcrypt('x'), 'role' => 'sneaker_owner', 'is_active' => 1]);
    }

    private function add(User $u, array $over = [])
    {
        return $this->actingAs($u)->post(route('expenses.store'), $over + [
            'amount' => '1500', 'description' => 'Пакеты 100 шт', 'category' => 'packaging', 'paid_from' => 'cash', 'spent_on' => now()->toDateString(),
        ]);
    }

    public function test_seller_adds_expense_with_receipt_and_sees_only_own(): void
    {
        $r = $this->add($this->op, ['paid_from' => 'own', 'receipt' => UploadedFile::fake()->image('chek.jpg')]);
        $e = ShopExpense::withoutGlobalScopes()->where('account_id', $this->acc)->latest('id')->firstOrFail();
        $r->assertRedirect(route('expenses.index', ['added' => $e->id]));
        $this->assertSame([1500.0, 'own', (int) $this->op->id], [(float) $e->amount, $e->paid_from, (int) $e->user_id]);
        Storage::disk('local')->assertExists($e->receipt_path);
        // руководителю — уведомление
        $this->assertTrue(UserNotification::withoutGlobalScopes()->where('user_id', $this->head->id)->where('type', 'shop_expense')->where('source_id', $e->id)->exists());

        // чужая трата (руководителя) продавцу не видна, своя — видна, чек открывается
        $this->add($this->head, ['description' => 'Аренда ноябрь', 'category' => 'rent', 'amount' => '40000', 'paid_from' => 'card']);
        $page = $this->actingAs($this->op)->get(route('expenses.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Пакеты 100 шт', $page);
        $this->assertStringNotContainsString('Аренда ноябрь', $page);
        $this->assertStringContainsString('Мне должны вернуть', $page);
        $this->actingAs($this->op)->get(route('expenses.receipt', $e))->assertOk();

        // руководитель видит обе и итог
        $page = $this->actingAs($this->head)->get(route('expenses.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Аренда ноябрь', $page);
        $this->assertStringContainsString('Пакеты 100 шт', $page);
        $this->assertStringContainsString('К возврату сотрудникам', $page);
    }

    public function test_validation_and_delete_rules(): void
    {
        $this->add($this->op, ['amount' => '0'])->assertSessionHasErrors('amount');
        $this->add($this->op, ['spent_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('spent_on');
        $this->add($this->op, ['paid_from' => ''])->assertSessionHasErrors('paid_from');

        $this->add($this->op);
        $mine = ShopExpense::withoutGlobalScopes()->where('account_id', $this->acc)->latest('id')->firstOrFail();
        $this->add($this->head, ['description' => 'Такси']);
        $heads = ShopExpense::withoutGlobalScopes()->where('account_id', $this->acc)->latest('id')->firstOrFail();

        // продавец не удаляет чужую и свою вчерашнюю; свою сегодняшнюю — да
        $this->actingAs($this->op)->delete(route('expenses.destroy', $heads))->assertForbidden();
        $old = ShopExpense::withoutGlobalScopes()->create(['account_id' => $this->acc, 'spent_on' => now()->subDays(3)->toDateString(), 'amount' => 300, 'category' => 'other',
            'paid_from' => 'cash', 'description' => 'старое', 'user_id' => $this->op->id]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->actingAs($this->op)->delete(route('expenses.destroy', $old))->assertForbidden();
        $this->actingAs($this->op)->delete(route('expenses.destroy', $mine))->assertRedirect();
        $this->assertNull(ShopExpense::withoutGlobalScopes()->find($mine->id));
        // руководитель — любую
        $this->actingAs($this->head)->delete(route('expenses.destroy', $old))->assertRedirect();
        $this->assertNull(ShopExpense::withoutGlobalScopes()->find($old->id));
        // продавец не отмечает «вернули»
        $this->actingAs($this->op)->post(route('expenses.reimburse', $heads))->assertForbidden();
    }

    public function test_reimburse_and_owner_dashboard_net(): void
    {
        $owner = $this->owner();
        $this->add($this->op, ['amount' => '2500', 'paid_from' => 'own', 'description' => 'Скотч и коробки']);
        $e = ShopExpense::withoutGlobalScopes()->where('account_id', $this->acc)->latest('id')->firstOrFail();
        $before = \App\Support\Sales\ShopExpenses::summary($this->acc, now()->startOfMonth(), now());
        $this->assertGreaterThanOrEqual(2500.0, $before['owed']);

        $this->actingAs($this->head)->post(route('expenses.reimburse', $e))->assertRedirect();
        $e->refresh();
        $this->assertNotNull($e->reimbursed_at);
        $after = \App\Support\Sales\ShopExpenses::summary($this->acc, now()->startOfMonth(), now());
        $this->assertEqualsWithDelta($before['owed'] - 2500.0, $after['owed'], 0.001);
        $this->actingAs($this->head)->post(route('expenses.reimburse', $e), ['undo' => 1])->assertRedirect();
        $this->assertNull($e->refresh()->reimbursed_at);

        // сводка владельца: «Чистыми останется» = прибыль с продаж − расходы за период
        $res = $this->actingAs($owner)->get(route('owner.dashboard', ['period' => 'month']))->assertOk();
        $this->assertStringContainsString('Чистыми останется', $res->getContent());
        $this->assertStringContainsString('Скотч и коробки', $res->getContent());
        $exp = $res->viewData('expenses');
        $this->assertEqualsWithDelta($after['total'], $exp['total'], 0.001);
        // владелец открывает страницу расходов и видит все траты
        $this->actingAs($owner)->get(route('expenses.index'))->assertOk()->assertSee('Скотч и коробки');
    }

    public function test_other_spaces_have_no_access(): void
    {
        $ceil = \App\Models\Account::create(['name' => 'Потолки (тест)'])->id;
        $admin = User::create(['account_id' => $ceil, 'name' => 'Админ', 'email' => 'a'.uniqid().'@t.local', 'password' => bcrypt('x'), 'role' => 'admin', 'is_active' => 1]);
        $this->actingAs($admin)->get(route('expenses.index'))->assertForbidden();
        // чужой аккаунт не открывает чек и не удаляет трату
        $this->add($this->op);
        $e = ShopExpense::withoutGlobalScopes()->where('account_id', $this->acc)->latest('id')->firstOrFail();
        $this->assertContains($this->actingAs($admin)->delete(route('expenses.destroy', $e))->status(), [403, 404]);
        $this->assertNotNull(ShopExpense::withoutGlobalScopes()->find($e->id));
    }
}
