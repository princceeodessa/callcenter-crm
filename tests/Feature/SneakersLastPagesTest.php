<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** «Дела», «Помощь», «Уведомления» кроссовок в каркасе «Нордика» (этап 7 редизайна, 28.10.2026) */
class SneakersLastPagesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tasks_by_prototype(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $pipeline = Pipeline::where('account_id', $head->account_id)->orderByDesc('is_default')->firstOrFail();
        $stage = PipelineStage::where('pipeline_id', $pipeline->id)->orderBy('sort')->firstOrFail();
        $deal = Deal::create(['account_id' => $head->account_id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'title' => 'TKTEST сделка', 'title_is_custom' => true, 'responsible_user_id' => $head->id]);
        DB::table('tasks')->insert([
            ['account_id' => $head->account_id, 'deal_id' => $deal->id, 'assigned_user_id' => $head->id, 'title' => 'TKTEST сегодня', 'status' => 'open', 'due_at' => now()->endOfDay()->subMinute(), 'created_at' => now(), 'updated_at' => now()],
            ['account_id' => $head->account_id, 'deal_id' => $deal->id, 'assigned_user_id' => $head->id, 'title' => 'TKTEST старое', 'status' => 'open', 'due_at' => now()->subDays(2), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $res = $this->actingAs($head)->get(route('tasks.index'))->assertOk()->assertViewIs('tasks.sneakers')
            ->assertSee('На сегодня')->assertSee('Просроченные')->assertSee('Ближайшие')->assertSee('Все сотрудники')
            ->assertSee('TKTEST сегодня')->assertSee('TKTEST старое')->assertSee('id="ntTpl"', false)
            ->assertSee('action="'.route('tasks.page.store').'"', false)->assertSee('TKTEST сделка');
        $this->assertCount(1, $res->viewData('selectedTasks')->where('title', 'TKTEST сегодня'));
        $this->assertCount(1, $res->viewData('previousTasks')->where('title', 'TKTEST старое'));

        // колл-центр — прежняя страница дел
        $op = User::where('role', 'operator')->first() ?? User::where('role', 'admin')->firstOrFail();
        $this->actingAs($op)->get(route('tasks.index'))->assertOk()->assertViewIs('tasks.index');
    }

    public function test_help_by_sections(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();

        $seller = $this->actingAs($head)->get(route('help.simple'))->assertOk()
            ->assertSee('Для продавца')->assertSee('Шаг 1 из 8')->assertSee('Как продать пару кроссовок')->assertSee('class="ns-hstep"', false)
            ->assertSee('class="ns-tip"', false)->assertSee('href="#s2"', false)->getContent();
        $this->assertSame(8, substr_count($seller, 'class="ns-panel ns-hdoc"'));

        $full = $this->actingAs($head)->get(route('help.index'))->assertOk()->assertSee('Раздел 1 из')->assertSee('Приёмка со сканером (ТСД)')->getContent();
        $this->assertGreaterThanOrEqual(30, substr_count($full, 'class="ns-panel ns-hdoc"'));
        $this->assertStringNotContainsString('верхней панели', $full);
    }

    public function test_notifications_page(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $n = UserNotification::create(['account_id' => $head->account_id, 'user_id' => $head->id, 'type' => 'sneaker_sale', 'title' => 'NTTEST продажа', 'body' => 'тест', 'is_read' => 0, 'payload' => []]);

        $this->actingAs($head)->get(route('notifications.index'))->assertOk()->assertViewIs('notifications.sneakers')
            ->assertSee('NTTEST продажа')->assertSee('Прочитать все')->assertSee('class="ns-html"', false);
        $this->actingAs($head)->post(route('notifications.read', ['notification' => $n, 'all' => 1]))->assertRedirect();
        $this->assertSame(0, UserNotification::where('user_id', $head->id)->where('is_read', 0)->count());

        $this->actingAs($head)->get(route('deals.index'))->assertOk()->assertSee('ns-html nd-html', false);
    }
}
