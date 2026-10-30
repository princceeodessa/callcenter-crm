<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Окно «Новое дело» на странице «Дела»: в списке сделок — открытые и закрытые за 30 дней (группами), с поиском.
 * 30.10.2026: заказ Лемана ПРО закрыли на «Замер назначен», клиент попросил перенести замер — дело было не к чему привязать.
 */
class TaskNewDealListTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $pipeline;
    private int $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест дел)'])->id;
        config(['owner.ceilings_account_id' => $this->acc]);
        [$this->pipeline, $this->stage] = $this->pipelineFor($this->acc);
    }

    private function pipelineFor(int $acc): array
    {
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $acc, 'name' => 'Воронка', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $stage = DB::table('pipeline_stages')->insertGetId(['account_id' => $acc, 'pipeline_id' => $pipeline, 'name' => 'Замер назначен', 'sort' => 40, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return [$pipeline, $stage];
    }

    private function user(string $role, ?int $acc = null): User
    {
        return User::create(['account_id' => $acc ?? $this->acc, 'name' => 'Виктория', 'email' => 'tn_'.$role.'_'.uniqid(), 'password' => Hash::make('password'), 'role' => $role, 'is_active' => true]);
    }

    private function deal(string $title, ?\DateTimeInterface $closedAt = null, string $phone = '79124556705', ?int $acc = null, ?array $pipe = null): int
    {
        $acc ??= $this->acc;
        [$pipeline, $stage] = $pipe ?? [$this->pipeline, $this->stage];
        $contact = DB::table('contacts')->insertGetId(['account_id' => $acc, 'name' => 'Ягул', 'phone' => $phone, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('deals')->insertGetId([
            'account_id' => $acc, 'pipeline_id' => $pipeline, 'stage_id' => $stage, 'contact_id' => $contact, 'title' => $title,
            'closed_at' => $closedAt, 'closed_result' => $closedAt ? 'won' : null, 'created_at' => now()->subDays(45), 'updated_at' => now(),
        ]);
    }

    public function test_recently_closed_deals_are_offered_in_the_new_task_window(): void
    {
        $op = $this->user('main_operator');
        $open = $this->deal('Потолок в зал');
        $closed = $this->deal('ЛЕМАНА Княженская 45', now()->subDay(), '79875024306');
        $old = $this->deal('Старая сделка', now()->subDays(40));
        $other = $this->deal('Чужая сделка', now()->subDay(), '79990000000', $acc = Account::create(['name' => 'Другой'])->id, $this->pipelineFor($acc));

        $res = $this->actingAs($op)->get(route('tasks.index'))->assertOk();
        $ids = $res->viewData('deals')->pluck('id')->all();
        $this->assertContains($open, $ids);
        $this->assertContains($closed, $ids);                                    // закрыта вчера — в списке
        $this->assertNotContains($old, $ids);                                    // 40 дней назад — нет
        $this->assertNotContains($other, $ids);                                  // чужой аккаунт — нет

        $html = $res->getContent();
        $this->assertStringContainsString('<optgroup label="Открытые">', $html);
        $this->assertStringContainsString('<optgroup label="Закрытые за 30 дней">', $html);
        $this->assertStringContainsString('ЛЕМАНА Княженская 45 | Ягул | 79875024306 · закрыта '.now()->subDay()->format('d.m'), $html);
        $this->assertStringContainsString('data-deal-filter', $html);           // поиск по списку
        $this->assertStringContainsString('_dealNodes', $html);
        $this->assertSame(1, substr_count($html, 'class="nd-field" data-deal-pick'));   // одно окно «Новое дело» — «Нордика»

        // дело к закрытой сделке создаётся
        $this->actingAs($op)->post(route('tasks.page.store'), [
            'deal_id' => $closed, 'title' => 'Перенос замера, набрать в пн', 'assigned_user_id' => $op->id, 'due_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tasks', ['deal_id' => $closed, 'title' => 'Перенос замера, набрать в пн', 'status' => 'open']);
    }

    public function test_without_closed_deals_the_list_has_no_groups(): void
    {
        $op = $this->user('operator');
        $this->deal('Потолок в зал');

        $html = $this->actingAs($op)->get(route('tasks.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Потолок в зал | Ягул | 79124556705</option>', $html);
        $this->assertStringNotContainsString('<optgroup', $html);
    }

    public function test_sneakers_new_task_window_has_closed_sales_and_search(): void
    {
        $sneakers = Account::create(['name' => 'Кроссовки (тест дел)'])->id;
        $pipe = $this->pipelineFor($sneakers);
        $head = $this->user('sneaker_head', $sneakers);
        $sale = $this->deal('Nike Dunk 42', now()->subDays(2), '79120001122', $sneakers, $pipe);

        $html = $this->actingAs($head)->get(route('tasks.index'))->assertOk()->getContent();
        $this->assertStringContainsString('class="ns-html"', $html);
        $this->assertStringContainsString('<optgroup label="Закрытые за 30 дней">', $html);
        $this->assertStringContainsString('<option value="'.$sale.'"', $html);
        $this->assertStringContainsString('data-deal-filter', $html);
        $this->assertStringContainsString('_dealNodes', $html);
    }
}
