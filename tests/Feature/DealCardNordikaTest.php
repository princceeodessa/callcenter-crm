<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Карточка сделки: у колл-центра — в стиле Nordika (шапка, действия, этапы кнопками, лента-таймлайн), у кроссовок —
 * прежняя. Лента у обеих собирается одинаково: события одного звонка (callid) — одной записью, с записью и расшифровкой.
 */
class DealCardNordikaTest extends TestCase
{
    use DatabaseTransactions;

    private function space(string $name): array
    {
        $acc = Account::create(['name' => $name])->id;
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $acc, 'name' => 'Воронка', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $stages = [];
        foreach (['lead' => ['Поступил лид', 10], 'nt' => ['Нецелевое', 15], 'measure' => ['Замер назначен', 40]] as $k => [$stageName, $sort]) {
            $stages[$k] = DB::table('pipeline_stages')->insertGetId(['account_id' => $acc, 'pipeline_id' => $pipeline, 'name' => $stageName, 'sort' => $sort, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        return [$acc, $pipeline, $stages];
    }

    /** Сделка с двумя событиями одного звонка (+ запись с расшифровкой), исходящим «занято» и заказом Лемана ПРО. */
    private function dealWithCalls(int $acc, int $pipeline, int $stage, int $userId): int
    {
        $contact = DB::table('contacts')->insertGetId(['account_id' => $acc, 'name' => 'Ольга', 'phone' => '79001112233', 'created_at' => now(), 'updated_at' => now()]);
        $deal = DB::table('deals')->insertGetId(['account_id' => $acc, 'pipeline_id' => $pipeline, 'stage_id' => $stage, 'contact_id' => $contact, 'responsible_user_id' => $userId, 'title' => 'Потолок в зал', 'created_at' => now()->subDay(), 'updated_at' => now()]);
        $act = fn (string $type, array $payload, string $body = '', int $min = 0) => DB::table('deal_activities')->insert([
            'account_id' => $acc, 'deal_id' => $deal, 'type' => $type, 'body' => $body, 'payload' => json_encode($payload),
            'created_at' => now()->subMinutes(60 - $min), 'updated_at' => now(),
        ]);
        $act('lead_form', ['provider' => 'hands'], 'Заказ Лемана ПРО № 7465162', 0);
        $act('call', ['callid' => 'abc', 'type' => 'in', 'phone' => '79001112233'], '', 10);
        $act('call', ['callid' => 'abc', 'type' => 'in', 'status' => 'success', 'user' => 'ivan_petrov', 'duration' => 75, 'phone' => '79001112233'], '', 11);
        $act('call', ['callid' => 'xyz', 'type' => 'out', 'status' => 'busy', 'phone' => '79001112233'], '', 20);
        DB::table('call_recordings')->insert(['account_id' => $acc, 'deal_id' => $deal, 'callid' => 'abc', 'recording_url' => 'https://rec.example.test/abc.mp3', 'transcript_status' => 'done', 'transcript_text' => 'Здравствуйте, хочу потолок', 'created_at' => now(), 'updated_at' => now()]);

        return $deal;
    }

    private function user(int $acc, string $role): User
    {
        return User::create(['account_id' => $acc, 'name' => 'Наталья Петрова', 'email' => 'dc_'.$role.'_'.uniqid(), 'password' => Hash::make('password'), 'role' => $role, 'is_active' => true]);
    }

    public function test_sneakers_card_keeps_old_markup_and_groups_calls(): void
    {
        [$acc, $pipeline, $stages] = $this->space('Кроссовки (тест карточки)');
        $head = $this->user($acc, 'sneaker_head');
        $deal = $this->dealWithCalls($acc, $pipeline, $stages['lead'], $head->id);

        $html = $this->actingAs($head)->get(route('deals.show', $deal))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="activity-call-card"'));          // два звонка, а не три события
        $this->assertStringContainsString('объединено событий: 2', $html);
        $this->assertStringContainsString('01:15', $html);
        $this->assertStringContainsString('Ivan Petrov', $html);
        $this->assertStringContainsString('Занято', $html);
        $this->assertStringContainsString('https://rec.example.test/abc.mp3', $html);
        $this->assertStringContainsString('Здравствуйте, хочу потолок', $html);
        $this->assertStringContainsString('id="dealStageSelect"', $html);
        $this->assertStringNotContainsString('nd-deal', $html);
    }

    public function test_call_center_card_is_nordika_and_keeps_forms(): void
    {
        [$acc, $pipeline, $stages] = $this->space('Потолки (тест карточки)');
        config(['owner.ceilings_account_id' => $acc]);   // нецелевые с категориями — у потолков
        $op = $this->user($acc, 'operator');
        $deal = $this->dealWithCalls($acc, $pipeline, $stages['lead'], $op->id);
        DB::table('tasks')->insert(['account_id' => $acc, 'deal_id' => $deal, 'assigned_user_id' => $op->id, 'title' => 'Перезвонить вечером', 'status' => 'open', 'due_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->actingAs($op)->get(route('deals.show', $deal))->assertOk()->getContent();

        // шапка и действия
        $this->assertStringContainsString('class="nd-panel nd-deal"', $html);
        $this->assertStringContainsString('<span class="nd-src" style="--src:var(--lemana)">', $html);   // заказ Лемана ПРО — чип
        $this->assertStringContainsString('href="tel:+79001112233"', $html);
        $this->assertStringContainsString('+7 900 111-22-33', $html);
        $this->assertMatchesRegularExpression('/data-stage-id="'.$stages['measure'].'">.*?Записать на замер/s', $html);
        // лента — та же группировка звонков, что и в прежней карточке
        $this->assertSame(2, substr_count($html, '<div class="nd-call">'));
        $this->assertStringContainsString('объединено событий: 2', $html);
        $this->assertStringContainsString('01:15', $html);
        $this->assertStringContainsString('Ivan Petrov', $html);
        $this->assertStringContainsString('Занято', $html);
        $this->assertStringContainsString('Здравствуйте, хочу потолок', $html);
        $this->assertStringContainsString('Заказ Лемана ПРО</div>', $html);
        // формы с прежними полями: этап (с категорией нецелевой), данные, закрытие, дела, голосовое
        $this->assertStringContainsString('id="dealStageForm"', $html);
        $this->assertStringContainsString('name="stage_id" id="dealStageSelect"', $html);
        $this->assertStringContainsString('name="non_target_category"', $html);
        $this->assertStringContainsString('action="'.route('deals.update', $deal).'"', $html);
        $this->assertStringContainsString('action="'.route('deals.close', $deal).'"', $html);
        $this->assertStringContainsString('name="result" value="extra_non_target"', $html);
        $this->assertStringContainsString('name="category"', $html);
        $this->assertStringContainsString('action="'.route('tasks.store', $deal).'"', $html);
        $this->assertStringContainsString('Перезвонить вечером', $html);
        $this->assertStringContainsString('action="'.route('deals.voice-note.store', $deal).'"', $html);
        $this->assertStringNotContainsString('class="activity-call-card"', $html);

        // этап кнопкой уходит той же формой, что и раньше
        $this->actingAs($op)->post(route('deals.stage', $deal), ['stage_id' => $stages['measure']])->assertRedirect();
        $this->assertSame($stages['measure'], (int) DB::table('deals')->where('id', $deal)->value('stage_id'));
    }
}
