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
        $this->assertStringContainsString('class="nd-closed-sum"', $html);                               // итог периода + причины нецелевых
        $this->assertStringContainsString('data-mode="range"', $html);                                   // период — календарём
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
        $this->assertStringContainsString('class="nd-tboard"', $html);                                   // дела — доской из трёх колонок
        $this->assertSame(2, substr_count($html, 'class="nd-card nd-tcard"'));
        $this->assertStringContainsString('Позвонить после 18', $html);
        $this->assertStringContainsString('nd-tdue nd-over', $html);                                     // просроченное — красным
        $this->assertStringContainsString('nd-tdue nd-today', $html);                                    // сегодняшнее — жёлтым
        $this->assertStringContainsString('id="ndTaskNewModal"', $html);
        $this->assertStringContainsString('action="'.route('tasks.page.store').'"', $html);
        $this->assertStringContainsString('data-param="focus_date"', $html);

        $html = $this->actingAs($head)->get(route('chats.index', ['c' => $conv]))->assertOk()->getContent();
        $this->assertStringContainsString('nd-chats nd-has-active', $html);
        $this->assertStringContainsString('<div class="nd-bub nd-in">', $html);
        $this->assertStringContainsString('<div class="nd-bub nd-out">', $html);
        $this->assertStringContainsString('id="sendForm"', $html);
        $this->assertStringContainsString('id="sendInput"', $html);
        $this->assertStringContainsString('id="ndEmojiPop"', $html);                                     // эмодзи в чатах
        $this->assertStringContainsString('js/nordika-emoji.js', $html);                               // набор в стиле iPhone — скриптом

        $html = $this->actingAs($head)->get(route('settings.users.index'))->assertOk()->getContent();
        $this->assertStringContainsString('class="nd-tbl nd-users"', $html);
        $this->assertStringContainsString('Руководитель</span>', $html);
        $this->assertStringContainsString('action="'.route('settings.users.store').'"', $html);

        $html = $this->actingAs($head)->get(route('reports.monthly'))->assertOk()->getContent();
        $this->assertStringContainsString('class="nd-toolbar"', $html);
        $this->assertStringContainsString('Этот месяц</a>', $html);
        $this->assertStringNotContainsString('<h4 class="mb-0">Отчёты</h4>', $html);

        foreach ([route('notifications.index'), route('deals.create'), route('nonclosures.index')] as $url) {
            $this->actingAs($head)->get($url)->assertOk()->assertSee('class="nd-page" data-route=', false);
        }
    }

    public function test_search_suggest_notifications_popover_and_deal_in_modal(): void
    {
        $op = $this->user('operator');
        $id = $this->deal('lead', 'Потолок в спальню');
        $this->deal('lead', 'Другая сделка');

        // подсказки поиска: по названию, по телефону 8…, по #id
        foreach (['спальн', '89124556705', '#'.$id] as $q) {
            $items = $this->actingAs($op)->getJson(route('deals.index', ['suggest' => 1, 'q' => $q]))->assertOk()->json('items');
            $this->assertContains($id, array_column($items, 'id'), $q);
        }
        $item = collect($this->actingAs($op)->getJson(route('deals.index', ['suggest' => 1, 'q' => 'спальн']))->json('items'))->firstWhere('id', $id);
        $this->assertSame(route('deals.show', $id), $item['url']);
        $this->assertSame('+7 912 455-67-05', $item['phone']);
        $this->assertSame([], $this->actingAs($op)->getJson(route('deals.index', ['suggest' => 1, 'q' => 'а']))->json('items'));

        // окно уведомлений: список JSON, «прочитать» и «прочитать все»
        $n1 = DB::table('user_notifications')->insertGetId(['account_id' => $this->acc, 'user_id' => $op->id, 'type' => 'task_due', 'title' => 'Пора выполнить дело', 'body' => 'Перезвонить', 'payload' => json_encode(['deal_id' => $id]), 'is_read' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_notifications')->insert(['account_id' => $this->acc, 'user_id' => $op->id, 'type' => 'task_due', 'title' => 'Ещё одно', 'body' => null, 'payload' => json_encode([]), 'is_read' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $list = $this->actingAs($op)->getJson(route('notifications.index', ['list' => 1]))->assertOk()->json();
        $this->assertSame(2, $list['unread_count']);
        $this->assertSame(route('deals.show', $id), collect($list['items'])->firstWhere('id', $n1)['url']);
        $this->assertSame('task_due', collect($list['items'])->firstWhere('id', $n1)['type']);       // для иконки в окне
        $this->actingAs($op)->postJson(route('notifications.read', $n1))->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, (int) DB::table('user_notifications')->where('user_id', $op->id)->where('is_read', 0)->count());
        $this->actingAs($op)->postJson(route('notifications.read', ['notification' => $n1, 'all' => 1]))->assertOk();
        $this->assertSame(0, (int) DB::table('user_notifications')->where('user_id', $op->id)->where('is_read', 0)->count());

        // каркас: окно сделки, окно уведомлений, кнопка звука, выбор дня календарём
        $html = $this->actingAs($op)->get(route('deals.kanban'))->assertOk()->getContent();
        foreach (['id="ndDealScrim"', 'id="ndDealFrame"', 'id="ndNotifPop"', 'id="ndSoundBtn"', 'js/nordika-crm.js', 'data-param="focus_date"'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }

        // сделка во всплывающем окне — страница в iframe без меню и верхней панели (и после редиректа формы тоже)
        $embed = $this->actingAs($op)->withHeaders(['Sec-Fetch-Dest' => 'iframe'])->get(route('deals.show', $id))->assertOk()->getContent();
        $this->assertStringContainsString('nd-html nd-embed', $embed);
        $this->assertStringContainsString('class="nd-panel nd-deal"', $embed);
        $this->assertStringNotContainsString('class="nd-side"', $embed);
        $this->assertStringNotContainsString('notifications/poll', $embed);
        $this->assertStringContainsString('nd-embed', $this->actingAs($op)->get(route('deals.show', ['deal' => $id, 'embed' => 1]))->getContent());
        $this->assertStringNotContainsString('id="ndEmbedDone"', $embed);

        // переоткрыли закрытую сделку в окне — окно закроется само (метка для nordika-crm.js), список под ним обновится
        $closed = $this->deal('done', 'Закрытая сделка', ['closed_at' => now(), 'closed_result' => 'won']);
        $after = $this->actingAs($op)->withHeaders(['Sec-Fetch-Dest' => 'iframe'])->from(route('deals.show', ['deal' => $closed, 'embed' => 1]))
            ->followingRedirects()->post(route('deals.stage', $closed), ['stage_id' => $this->stage['lead']])->assertOk()->getContent();
        $this->assertNull(DB::table('deals')->where('id', $closed)->value('closed_at'));
        $this->assertStringContainsString('id="ndEmbedDone" data-msg="Сделка переоткрыта — «', $after);
        // обычная смена этапа открытой сделки окно не закрывает
        $plain = $this->actingAs($op)->withHeaders(['Sec-Fetch-Dest' => 'iframe'])->from(route('deals.show', ['deal' => $id, 'embed' => 1]))
            ->followingRedirects()->post(route('deals.stage', $id), ['stage_id' => $this->stage['measure']])->assertOk()->getContent();
        $this->assertStringNotContainsString('id="ndEmbedDone"', $plain);
        $this->assertStringNotContainsString('nd-embed', $this->actingAs($op)->withHeaders(['Sec-Fetch-Dest' => 'document'])->get(route('deals.show', $id))->getContent());
    }

    public function test_calendar_page_layout(): void
    {
        $head = $this->user('main_operator');
        $html = $this->actingAs($head)->get(route('calendar.index'))->assertOk()->getContent();
        foreach (['class="nd-cal-page"', 'id="ndCalAgenda"', 'id="ccrmCalendar"', 'id="filterUser"', 'id="btnNew"', 'id="measurementModal"'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertStringContainsString('Запланирован', $html);
    }

    public function test_sneakers_keep_old_pages(): void
    {
        $sneakers = Account::create(['name' => 'Кроссовки (тест страниц)'])->id;
        $head = $this->user('sneaker_head', 'Рук кроссовок', $sneakers);

        $html = $this->actingAs($head)->get(route('tasks.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('nd-tk-stats', $html);
        $this->assertStringContainsString('navbar-dark', $html);

        // «Пользователи» кроссовок — уже в их собственном каркасе «Нордика» (layouts/sneakers, этап 1), не в каркасе колл-центра
        $html = $this->actingAs($head)->get(route('settings.users.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('nd-users', $html);
        $this->assertStringNotContainsString('nd-html', $html);
        $this->assertStringContainsString('class="ns-html"', $html);
        $this->assertStringContainsString('Новый сотрудник', $html);

        $html = $this->actingAs($head)->get(route('deals.closed'))->assertOk()->getContent();
        $this->assertStringNotContainsString('class="nd-tbl"', $html);
    }
}
