<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Ui\Sneakers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** «Продажи за день», «Отчёт», «Аналитика», «1С» в каркасе «Нордика» (этап 5 редизайна, 28.10.2026) */
class SneakersReportsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_and_analytics_are_head_only_and_by_prototype(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $operator = User::where('role', 'sneaker_operator')->where('account_id', $head->account_id)->firstOrFail();

        $this->actingAs($head)->get(route('sneaker.report', ['month' => now()->format('Y-m')]))->assertOk()
            ->assertSee('class="ns-kpis"', false)->assertSee('Динамика за 6 месяцев')->assertSee('Воронка продаж')
            ->assertSee('Залежавшийся товар')->assertSee('class="ns-select"', false);
        $this->actingAs($head)->get(route('warehouse.analytics'))->assertOk()
            ->assertSee('Сезонность продаж')->assertSee('Тепловая карта продаж')->assertSee('Размерная сетка склада')
            ->assertSee('Мёртвый груз')->assertSee('ABC-анализ');

        // отчёт показывал продавцу прибыль и себестоимость — теперь закрыт (решение 28.10.2026)
        $this->actingAs($operator)->get(route('sneaker.report'))->assertForbidden();
        $this->actingAs($operator)->get(route('sneaker.report.export'))->assertForbidden();
    }

    public function test_day_page_by_prototype(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $operator = User::where('role', 'sneaker_operator')->where('account_id', $head->account_id)->firstOrFail();

        $this->actingAs($head)->get(route('sale.day'))->assertOk()
            ->assertSee('ns-kpis ns-k6', false)->assertSee('Выручка по дням')->assertSee('id="dPick"', false);
        $this->actingAs($operator)->get(route('sale.day', ['date' => now()->subDay()->toDateString()]))->assertOk()
            ->assertSee('ns-kpis ns-k5', false)->assertSee('>Сегодня</a>', false)->assertDontSee('Прибыль');
    }

    public function test_source_chip(): void
    {
        $this->assertStringContainsString('--src:var(--ig)">Instagram<', Sneakers::srcChip('Instagram'));
        $this->assertStringContainsString('>не указан<', Sneakers::srcChip(''));
        $this->assertStringContainsString('>не указан<', Sneakers::srcChip('Не указан'));
        $this->assertStringContainsString('&lt;b&gt;', Sneakers::srcChip('<b>'));
    }
}
