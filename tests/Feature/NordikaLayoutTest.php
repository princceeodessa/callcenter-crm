<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Редизайн «Nordika» (архив дизайнера 26.10.2026): у сотрудников потолков — каркас с боковым меню по ролям и новый канбан
 * (чип источника, телефон «+7 …», «Перенос: [кружок] Имя»); у кроссовок и на входе — прежняя разметка.
 */
class NordikaLayoutTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $pipeline;
    private array $stage = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест Nordika)'])->id;
        $this->pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['lead' => ['Поступил лид', 10], 'measure' => ['Замер назначен', 40]] as $k => [$name, $sort]) {
            $this->stage[$k] = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'name' => $name, 'sort' => $sort, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function user(string $role, string $name = 'Наталья Петрова', ?int $account = null): User
    {
        return User::create(['account_id' => $account ?? $this->acc, 'name' => $name, 'email' => 'nd_'.$role.'_'.uniqid(), 'password' => Hash::make('password'), 'role' => $role, 'is_active' => true]);
    }

    private function deal(string $stage, string $title, ?string $phone = null): int
    {
        $contact = $phone ? DB::table('contacts')->insertGetId(['account_id' => $this->acc, 'name' => null, 'phone' => $phone, 'created_at' => now(), 'updated_at' => now()]) : null;

        return DB::table('deals')->insertGetId([
            'account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage[$stage], 'contact_id' => $contact,
            'title' => $title, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_operator_kanban_uses_new_shell_and_cards(): void
    {
        $op = $this->user('operator');
        $mover = $this->user('main_operator', 'Виктория Соковикова');
        $lemana = $this->deal('measure', 'Ильфат — Лемана ПРО', '89199098555');
        DB::table('deal_activities')->insert(['account_id' => $this->acc, 'deal_id' => $lemana, 'type' => 'lead_form', 'body' => 'Замер потолка', 'payload' => json_encode(['provider' => 'hands']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('deal_stage_history')->insert(['account_id' => $this->acc, 'deal_id' => $lemana, 'from_stage_id' => $this->stage['lead'], 'to_stage_id' => $this->stage['measure'], 'changed_by_user_id' => $mover->id, 'changed_at' => now()]);
        $this->deal('lead', 'Без телефона');
        $vk = $this->deal('lead', 'Татьяна из ВК');
        DB::table('conversations')->insert(['account_id' => $this->acc, 'deal_id' => $vk, 'channel' => 'vk', 'external_id' => 'vk-1', 'status' => 'open', 'unread_count' => 2, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->actingAs($op)->get(route('deals.kanban'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-deal-id="'.$vk.'".*?<span class="nd-src" style="--src:var\(--vk\)">.*?VK<\/span>.*?nd-attn[^>]*>.*?2<\/span>/s', $html);

        foreach (['class="nd-html"', 'class="nd-side"', 'class="nd-board"', 'id="kanbanSearch"', 'id="kbModeRow"', 'kanban-list', 'id="navNotifBadge"'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertStringContainsString('Лемана</span>', $html);                    // чип источника Лемана ПРО
        $this->assertStringContainsString('+7 919 909-85-55', $html);                // телефон в виде дизайнера
        $this->assertStringContainsString('nd-noph', $html);                          // «Без телефона»
        $this->assertStringContainsString('ещё не перемещали', $html);
        $this->assertMatchesRegularExpression('/Перенос:<\/span> <span class="nd-mini-av"[^>]*>ВС<\/span> Виктория/u', $html);
        $this->assertMatchesRegularExpression('/<a href="[^"]*\/deals\/kanban" class="nd-active">/', $html);
        // меню оператора: без «Пользователей» и «Интеграций»; верхняя панель прежнего вида не рисуется
        $this->assertStringNotContainsString('Пользователи</a>', $html);
        $this->assertStringNotContainsString('Интеграции</a>', $html);
        $this->assertStringNotContainsString('navbar-dark', $html);
    }

    public function test_admin_menu_and_measurer_menu(): void
    {
        $admin = $this->actingAs($this->user('admin', 'Админ'))->get(route('deals.index'))->assertOk()->getContent();
        foreach (['Интеграции</a>', 'Импорт Bitrix</a>', 'Пользователи</a>', 'Проектировка</a>', 'class="nd-page"'] as $needle) {
            $this->assertStringContainsString($needle, $admin, $needle);
        }

        $measurer = $this->actingAs($this->user('measurer', 'Замерщик Иван'))->get(route('calendar.index'))->assertOk()->getContent();
        $this->assertStringContainsString('class="nd-side"', $measurer);
        $this->assertStringContainsString('Календарь</a>', $measurer);
        $this->assertStringContainsString('Отчёты</a>', $measurer);
        $this->assertStringNotContainsString('Канбан</a>', $measurer);
        $this->assertStringNotContainsString('id="navNotifBadge"', $measurer);   // у замерщика колокольчика не было
    }

    /** Кроссовки — свой каркас «Нордика» (layouts.sneakers, с 28.10.2026), не каркас колл-центра; вход — прежний */
    public function test_sneakers_have_own_layout_and_login_keeps_old_layout(): void
    {
        $sneakers = Account::create(['name' => 'Кроссовки (тест Nordika)'])->id;
        $pipe = DB::table('pipelines')->insertGetId(['account_id' => $sneakers, 'name' => 'Продажи', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pipeline_stages')->insert(['account_id' => $sneakers, 'pipeline_id' => $pipe, 'name' => 'Новый', 'sort' => 10, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->actingAs($this->user('sneaker_head', 'Рук кроссовок', $sneakers))->get(route('deals.kanban'))->assertOk()->getContent();
        $this->assertStringContainsString('class="ns-html"', $html);
        $this->assertStringContainsString('class="ns-board"', $html);
        $this->assertStringNotContainsString('navbar-dark', $html);
        $this->assertStringNotContainsString('nd-html', $html);
        $this->assertStringNotContainsString('class="nd-board"', $html);
        $this->assertStringNotContainsString('class="nd-card', $html);

        auth()->logout();
        $this->assertStringNotContainsString('nd-html', $this->get(route('login'))->getContent());
    }
}
