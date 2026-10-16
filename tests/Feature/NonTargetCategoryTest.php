<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Нецелевые потолков по категориям: без категории нельзя закрыть «Не целевой» и перевести на «Нецелевое» (ни в карточке,
 * ни в канбане); «Завершённые» показывают исходы месяца колонками — целевой записанный, целевой отказ и 6 категорий.
 */
class NonTargetCategoryTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $pipeline;
    private array $stage = [];
    private User $op;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест категорий)'])->id;
        config(['owner.ceilings_account_id' => $this->acc]);
        $this->pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['lead' => ['Поступил лид', 10, 0], 'nt' => ['Нецелевое', 15, 0], 'measure' => ['Замер назначен', 40, 0], 'done' => ['Завершить сделку', 60, 1]] as $k => [$name, $sort, $final]) {
            $this->stage[$k] = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'name' => $name, 'sort' => $sort, 'is_final' => $final, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->op = User::create(['account_id' => $this->acc, 'name' => 'Оператор (тест)', 'email' => 'nt_op_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'operator', 'is_active' => true]);
    }

    private function deal(string $stage = 'lead', array $extra = []): int
    {
        return DB::table('deals')->insertGetId($extra + [
            'account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage[$stage],
            'title' => 'Звонки: 79000000000', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_closing_as_non_target_needs_a_category(): void
    {
        $id = $this->deal();
        $this->actingAs($this->op)->from(route('deals.show', $id))
            ->post(route('deals.close', $id), ['result' => 'extra_non_target'])
            ->assertRedirect(route('deals.show', $id))->assertSessionHasErrors('category');
        $this->assertNull(DB::table('deals')->where('id', $id)->value('closed_at'));

        $this->actingAs($this->op)->post(route('deals.close', $id), ['result' => 'extra_non_target', 'category' => 'claim', 'reason' => 'протёк шов']);
        $d = DB::table('deals')->where('id', $id)->first();
        $this->assertSame(['extra_non_target', 'claim'], [$d->closed_result, $d->non_target_category]);
        $this->assertStringContainsString('Претензии', DB::table('deal_activities')->where('deal_id', $id)->where('type', 'deal_closed')->value('body'));

        // «Отдел светотехники» — тоже категория нецелевого (в списке при закрытии)
        $light = $this->deal();
        $this->actingAs($this->op)->get(route('deals.show', $light))->assertOk()->assertSee('Отдел светотехники');
        $this->actingAs($this->op)->post(route('deals.close', $light), ['result' => 'extra_non_target', 'category' => 'lighting']);
        $this->assertSame('lighting', DB::table('deals')->where('id', $light)->value('non_target_category'));

        // «Отказ» и «Успешно» — без категории
        $lost = $this->deal();
        $this->actingAs($this->op)->post(route('deals.close', $lost), ['result' => 'lost', 'category' => 'spam']);
        $this->assertSame(['lost', null], [DB::table('deals')->where('id', $lost)->value('closed_result'), DB::table('deals')->where('id', $lost)->value('non_target_category')]);

        // карточка показывает выбор категорий
        $this->actingAs($this->op)->get(route('deals.show', $this->deal()))->assertOk()
            ->assertSee('почему нецелевая')->assertSee('Запрос на связь с замерщиком');
    }

    public function test_moving_to_the_non_target_stage_needs_a_category(): void
    {
        $id = $this->deal();
        // канбан: без категории — 422, сделка на месте
        $this->actingAs($this->op)->postJson(route('deals.move', $id), ['to_stage_id' => $this->stage['nt']])
            ->assertStatus(422)->assertJson(['need_category' => true]);
        $this->assertSame($this->stage['lead'], (int) DB::table('deals')->where('id', $id)->value('stage_id'));

        $this->actingAs($this->op)->postJson(route('deals.move', $id), ['to_stage_id' => $this->stage['nt'], 'non_target_category' => 'dealer'])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertSame([$this->stage['nt'], 'dealer'], [(int) DB::table('deals')->where('id', $id)->value('stage_id'), DB::table('deals')->where('id', $id)->value('non_target_category')]);

        // карточка: смена категории на том же этапе
        $this->actingAs($this->op)->post(route('deals.stage', $id), ['stage_id' => $this->stage['nt'], 'non_target_category' => 'installation']);
        $this->assertSame('installation', DB::table('deals')->where('id', $id)->value('non_target_category'));

        // ушла с «Нецелевое» — категория снимается
        $this->actingAs($this->op)->postJson(route('deals.move', $id), ['to_stage_id' => $this->stage['measure']])->assertOk();
        $this->assertNull(DB::table('deals')->where('id', $id)->value('non_target_category'));

        // карточка: на «Нецелевое» без категории — ошибка
        $this->actingAs($this->op)->from(route('deals.show', $id))->post(route('deals.stage', $id), ['stage_id' => $this->stage['nt']])
            ->assertSessionHasErrors('non_target_category');
        $this->assertSame($this->stage['measure'], (int) DB::table('deals')->where('id', $id)->value('stage_id'));

        $this->actingAs($this->op)->get(route('deals.kanban'))->assertOk()->assertSee('id="ntPicker"', false)->assertSee('Реклама и спам');
    }

    public function test_closed_page_shows_outcomes_by_category(): void
    {
        $at = now()->startOfMonth()->addDays(2)->toDateTimeString();
        $won = $this->deal('done', ['closed_at' => $at, 'closed_result' => 'won']);
        $bookedLost = $this->deal('done', ['closed_at' => $at, 'closed_result' => 'lost']);           // был на замере — целевой записанный
        DB::table('deal_stage_history')->insert(['account_id' => $this->acc, 'deal_id' => $bookedLost, 'from_stage_id' => $this->stage['lead'], 'to_stage_id' => $this->stage['measure'], 'changed_at' => $at]);
        $lost = $this->deal('done', ['closed_at' => $at, 'closed_result' => 'lost']);
        $claim = $this->deal('done', ['closed_at' => $at, 'closed_result' => 'extra_non_target', 'non_target_category' => 'claim', 'title' => 'Претензия по шву']);
        $old = $this->deal('done', ['closed_at' => $at, 'closed_result' => 'extra_non_target']);      // закрыта до категорий

        $res = $this->actingAs($this->op)->get(route('deals.closed'))->assertOk();
        $this->assertSame(['booked' => 2, 'lost' => 1, 'dealer' => 0, 'installation' => 0, 'extra_works' => 0, 'spam' => 0, 'measurer' => 0, 'claim' => 1, 'lighting' => 0, 'none' => 1], $res->viewData('outcomeCounts'));
        $res->assertSee('Целевой записанный')->assertSee('Нецелевые без категории');

        $ids = collect($this->actingAs($this->op)->get(route('deals.closed', ['category' => 'claim']))->viewData('deals')->items())->pluck('id')->all();
        $this->assertSame([$claim], $ids);
        $ids = collect($this->actingAs($this->op)->get(route('deals.closed', ['category' => 'booked']))->viewData('deals')->items())->pluck('id')->sort()->values()->all();
        $this->assertSame([$won, $bookedLost], $ids);
        $this->assertNotContains($lost, $ids);
        $this->assertNotContains($old, $ids);
    }

    /** «Завершённые» — период «с … по …» вместо месяца: и список, и колонки исходов. */
    public function test_closed_page_by_date_range(): void
    {
        $before = $this->deal('done', ['closed_at' => '2026-10-14 18:00:00', 'closed_result' => 'extra_non_target']);
        $first = $this->deal('done', ['closed_at' => '2026-10-15 09:30:00', 'closed_result' => 'extra_non_target', 'non_target_category' => 'dealer']);
        $last = $this->deal('done', ['closed_at' => '2026-10-16 23:50:00', 'closed_result' => 'won']);
        $after = $this->deal('done', ['closed_at' => '2026-10-17 00:10:00', 'closed_result' => 'lost']);

        $res = $this->actingAs($this->op)->get(route('deals.closed', ['from' => '2026-10-15', 'to' => '2026-10-16']))->assertOk();
        $ids = collect($res->viewData('deals')->items())->pluck('id')->sort()->values()->all();
        $this->assertSame([$first, $last], $ids);
        $this->assertSame(1, $res->viewData('outcomeCounts')['dealer']);
        $this->assertSame(1, $res->viewData('outcomeCounts')['booked']);
        $this->assertSame(0, $res->viewData('outcomeCounts')['none']);
        $res->assertSee('Исходы закрытых за 15.10.2026 – 16.10.2026')->assertSee('value="2026-10-15"', false);

        // колонка-фильтр сохраняет период
        $res->assertSee(route('deals.closed', ['from' => '2026-10-15', 'to' => '2026-10-16', 'category' => 'dealer']));
        // старая ссылка по месяцу работает как раньше
        $this->assertCount(4, $this->actingAs($this->op)->get(route('deals.closed', ['month' => '2026-10']))->viewData('deals')->items());
    }

    public function test_other_spaces_close_non_target_without_a_category(): void
    {
        config(['owner.ceilings_account_id' => -1]);    // это пространство — не потолки
        $id = $this->deal();
        $this->actingAs($this->op)->post(route('deals.close', $id), ['result' => 'extra_non_target'])->assertSessionHasNoErrors();
        $this->assertSame('extra_non_target', DB::table('deals')->where('id', $id)->value('closed_result'));
        $this->actingAs($this->op)->get(route('deals.closed'))->assertOk()->assertDontSee('Исходы закрытых за месяц');
    }
}
