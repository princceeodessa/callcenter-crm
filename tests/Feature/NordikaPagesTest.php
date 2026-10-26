<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Редизайн «Nordika», этап 3: список сделок, завершённые, дела, чаты, пользователи, отчёты и прочие страницы колл-центра —
 * в новом виде (таблицы с чипами и метками этапа, страницы по-русски, дела строками, чаты пузырями); у кроссовок — прежние.
 */
class NordikaPagesTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $pipeline;
    private array $stage = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест страниц)'])->id;
        config(['owner.ceilings_account_id' => $this->acc]);
        $this->pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['lead' => ['Поступил лид / Назначить ответственного', 10, 0], 'nt' => ['Нецелевое', 15, 0], 'measure' => ['Замер назначен', 40, 0], 'done' => ['Завершить сделку', 60, 1]] as $k => [$name, $sort, $final]) {
            $this->stage[$k] = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'name' => $name, 'sort' => $sort, 'is_final' => $final, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function user(string $role, string $name = 'Наталья Петрова', ?int $acc = null): User
    {
        return User::create(['account_id' => $acc ?? $this->acc, 'name' => $name, 'email' => 'np_'.$role.'_'.uniqid(), 'password' => Hash::make('password'), 'role' => $role, 'is_active' => true]);
    }

    private function deal(string $stage, string $title, array $extra = []): int
    {
        $contact = DB::table('contacts')->insertGetId(['account_id' => $this->acc, 'name' => 'Ольга', 'phone' => '79124556705', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('deals')->insertGetId($extra + [
            'account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage[$stage], 'contact_id' => $contact,
            'title' => $title, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_deal_list_and_closed_pages(): void
    {
        $op = $this->user('operator');
        $id = $this->deal('measure', 'Потолок в зал', ['responsible_user_id' => $op->id, 'is_unread' => 1]);   // непрочитанная — вверху списка
        DB::table('deal_activities')->insert(['account_id' => $this->acc, 'deal_id' => $id, 'type' => 'lead_form', 'body' => 'Заказ', 'payload' => json_encode(['provider' => 'hands']), 'created_at' => now(), 'updated_at' => now()]);
        for ($i = 0; $i < 26; $i++) {
            $this->deal('lead', 'Клиент '.$i);
        }

        $html = $this->actingAs($op)->get(route('deals.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<table class="nd-tbl">', $html);
        $this->assertStringContainsString('data-nd-href="'.route('deals.show', $id).'"', $html);
        $this->assertStringContainsString('<span class="nd-src" style="--src:var(--lemana)">', $html);   // Лемана ПРО и в списке
        $this->assertStringContainsString('Замер назначен</span>', $html);
        $this->assertStringContainsString('+7 912 455-67-05', $html);
        $this->assertStringContainsString('1–25 из 27', $html);                                          // страницы — по-русски
        $this->assertStringNotContainsString('Showing', $html);
        $this->assertStringContainsString('id="dealSearchForm"', $html);
        $this->assertStringContainsString('form="dealSearchForm" name="q"', $html);

        $closedAt = now()->startOfMonth()->addDay()->toDateTimeString();
        $won = $this->deal('done', 'Договор на кухню', ['closed_at' => $closedAt, 'closed_result' => 'won']);
        $this->deal('done', 'Претензия', ['closed_at' => $closedAt, 'closed_result' => 'extra_non_target', 'non_target_category' => 'claim']);
        $html = $this->actingAs($op)->get(route('deals.closed'))->assertOk()->getContent();
        $this->assertStringContainsString('class="nd-kpis nd-kpis-auto"', $html);
        $this->assertStringContainsString('data-nd-href="'.route('deals.show', $won).'"', $html);
        $this->assertStringContainsString('Успешно</span>', $html);
        $this->assertStringContainsString('Претензии', $html);
    }

    public function test_tasks_chats_users_reports_and_other_pages(): void
    {
        $head = $this->user('main_operator', 'Виктория Соковикова');
        $deal = $this->deal('lead', 'Перезвонить клиенту');
        DB::table('tasks')->insert([
            ['account_id' => $this->acc, 'deal_id' => $deal, 'assigned_user_id' => $head->id, 'title' => 'Позвонить после 18', 'status' => 'open', 'due_at' => now()->endOfDay()->subMinute(), 'created_at' => now(), 'updated_at' => now()],
            ['account_id' => $this->acc, 'deal_id' => $deal, 'assigned_user_id' => $head->id, 'title' => 'Старое дело', 'status' => 'open', 'due_at' => now()->subDays(2), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $conv = DB::table('conversations')->insertGetId(['account_id' => $this->acc, 'deal_id' => $deal, 'channel' => 'telegram', 'external_id' => 'tg-1', 'status' => 'open', 'unread_count' => 1, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('messages')->insert([
            ['account_id' => $this->acc, 'conversation_id' => $conv, 'direction' => 'in', 'author' => 'tg:1', 'body' => 'Здравствуйте, сколько стоит?', 'created_at' => now()->subMinute(), 'updated_at' => now()],
            ['account_id' => $this->acc, 'conversation_id' => $conv, 'direction' => 'out', 'author' => 'Виктория', 'body' => 'Добрый день! Подскажите адрес', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $html = $this->actingAs($head)->get(route('tasks.index'))->assertOk()->getContent();
        $this->assertStringContainsString('class="nd-tk-stats"', $html);
        $this->assertSame(2, substr_count($html, '<div class="nd-tk" '));
        $this->assertStringContainsString('Позвонить после 18', $html);
        $this->assertStringContainsString('nd-tkdue nd-over', $html);                                    // просроченное — красным
        $this->assertStringContainsString('action="'.route('tasks.page.store').'"', $html);

        $html = $this->actingAs($head)->get(route('chats.index', ['c' => $conv]))->assertOk()->getContent();
        $this->assertStringContainsString('nd-chats nd-has-active', $html);
        $this->assertStringContainsString('<div class="nd-bub nd-in">', $html);
        $this->assertStringContainsString('<div class="nd-bub nd-out">', $html);
        $this->assertStringContainsString('id="sendForm"', $html);
        $this->assertStringContainsString('id="sendInput"', $html);

        $html = $this->actingAs($head)->get(route('settings.users.index'))->assertOk()->getContent();
        $this->assertStringContainsString('class="nd-tbl nd-users"', $html);
        $this->assertStringContainsString('Руководитель</span>', $html);
        $this->assertStringContainsString('action="'.route('settings.users.store').'"', $html);

        $html = $this->actingAs($head)->get(route('reports.monthly'))->assertOk()->getContent();
        $this->assertStringContainsString('class="nd-toolbar"', $html);
        $this->assertStringContainsString('Этот месяц</a>', $html);
        $this->assertStringNotContainsString('<h4 class="mb-0">Отчёты</h4>', $html);

        foreach ([route('calendar.index'), route('notifications.index'), route('deals.create'), route('nonclosures.index')] as $url) {
            $this->actingAs($head)->get($url)->assertOk()->assertSee('class="nd-page" data-route=', false);
        }
    }

    public function test_sneakers_keep_old_pages(): void
    {
        $sneakers = Account::create(['name' => 'Кроссовки (тест страниц)'])->id;
        $head = $this->user('sneaker_head', 'Рук кроссовок', $sneakers);

        $html = $this->actingAs($head)->get(route('tasks.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('nd-tk-stats', $html);
        $this->assertStringContainsString('navbar-dark', $html);

        $html = $this->actingAs($head)->get(route('settings.users.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('nd-users', $html);
        $this->assertStringContainsString('Добавить пользователя', $html);

        $html = $this->actingAs($head)->get(route('deals.closed'))->assertOk()->getContent();
        $this->assertStringNotContainsString('class="nd-tbl"', $html);
    }
}
