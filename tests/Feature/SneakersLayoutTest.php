<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Support\Ui\Sneakers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Редизайн «Нордика» кроссовочного отдела (пакет дизайнера 28.10.2026), этап 1: каркас layouts/sneakers (боковое меню по
 * ролям, шапка с темой, звуком и колокольчиком) и первая страница на нём — «Пользователи». Колл-центр не меняется.
 */
class SneakersLayoutTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Кроссовки (тест Нордики)'])->id;
    }

    private function user(string $role, string $name, ?int $account = null): User
    {
        return User::create(['account_id' => $account ?? $this->acc, 'name' => $name, 'email' => 'ns_'.$role.'_'.uniqid(), 'password' => Hash::make('password'), 'role' => $role, 'is_active' => true]);
    }

    public function test_users_page_in_the_sneakers_shell(): void
    {
        $head = $this->user('sneaker_head', 'Руководитель Кроссовок');
        $seller = $this->user('sneaker_operator', 'Ксения Петрова');
        // продажа со склада в этом месяце — у продавца; прошлый месяц не считается
        $pipe = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Продажи', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $stage = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $pipe, 'name' => 'Продано', 'sort' => 50, 'is_final' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([now(), now()->subMonthNoOverflow()->startOfMonth()] as $at) {
            DB::table('deals')->insert(['account_id' => $this->acc, 'pipeline_id' => $pipe, 'stage_id' => $stage, 'title' => 'Продажа', 'responsible_user_id' => $seller->id,
                'stock_deducted_at' => $at, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('warehouse_items')->insert([
            ['account_id' => $this->acc, 'brand' => 'Nike', 'model' => 'Air Max', 'size' => '42', 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['account_id' => $this->acc, 'brand' => 'Nike', 'model' => 'Air Max', 'size' => '43', 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['account_id' => $this->acc, 'brand' => 'Puma', 'model' => 'RS-X', 'size' => '40', 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $html = $this->actingAs($head)->get(route('settings.users.index'))->assertOk()->getContent();
        // каркас: без Bootstrap и без верхней панели старого вида, меню по группам, шапка
        $this->assertStringContainsString('class="ns-html"', $html);
        $this->assertStringContainsString('css/nordika-sneakers.css', $html);
        $this->assertStringContainsString('js/nordika-sneakers.js', $html);
        $this->assertStringNotContainsString('bootstrap.min.css', $html);
        $this->assertStringNotContainsString('navbar-dark', $html);
        $this->assertStringNotContainsString('nd-html', $html);
        foreach (['Магазин', 'Склад', 'Сделки', 'Руководителю', 'Система'] as $group) {
            $this->assertStringContainsString('<div class="ns-nav-h">'.$group.'</div>', $html, $group);
        }
        foreach (['Продажа', 'Продажи за день', 'Скан', 'Приёмка', 'Закупки', 'Аналитика', 'Продажи · канбан', 'Дела', 'Сводка', 'Отчёты', 'Пользователи', 'Помощь'] as $item) {
            $this->assertStringContainsString($item, $html, $item);
        }
        $this->assertMatchesRegularExpression('~Склад<span class="ns-cnt">2</span>~u', $html);   // 2 модели (бренд + модель)
        $this->assertStringContainsString('id="nsSoundBtn"', $html);
        $this->assertStringContainsString('id="nsBellBtn"', $html);
        $this->assertStringContainsString('"pollUrl"', $html);
        $this->assertStringNotContainsString('id="nsBrandMenu"', $html);                 // переключатель отдела — только владельцу всех бизнесов
        // таблица сотрудников, как в прототипе
        $this->assertStringContainsString('— кроссовочный отдел · 2 активных из 2', $html);
        $this->assertStringContainsString('Ксения Петрова', $html);
        $this->assertStringContainsString('видит закуп', $html);
        $this->assertStringContainsString('только продажа', $html);
        $this->assertMatchesRegularExpression('~Ксения Петрова.*?<td class="ns-num"><b>1</b></td>~su', $html);
        $this->assertStringContainsString('data-ns-pw="'.route('settings.users.password', $seller).'"', $html);
        $this->assertStringContainsString('Отключить', $html);
        $this->assertStringContainsString('id="nsUserNew"', $html);
    }

    public function test_head_creates_only_sneakers_staff(): void
    {
        $head = $this->user('sneaker_head', 'Руководитель Кроссовок');
        $this->actingAs($head)->from(route('settings.users.index'))->post(route('settings.users.store'), [
            'first_name' => 'Иван', 'last_name' => 'Админов', 'login' => 'ns_admin_'.uniqid(), 'password' => 'secret12', 'role' => 'admin', 'is_active' => '1',
        ])->assertSessionHasErrors('role');
        $login = 'ns_seller_'.uniqid();
        $this->actingAs($head)->post(route('settings.users.store'), [
            'first_name' => 'Анна', 'last_name' => 'Продавцова', 'login' => $login, 'password' => 'secret12', 'role' => 'sneaker_operator', 'is_active' => '1',
        ])->assertRedirect(route('settings.users.index'));
        $this->assertSame('sneaker_operator', User::where('email', $login)->value('role'));
        // ошибка формы — окно «Новый сотрудник» откроется само с текстом ошибки
        $this->actingAs($head)->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag(['login' => 'Логин занят']))])
            ->get(route('settings.users.index'))->assertSee('Логин занят');
    }

    public function test_menu_by_role_and_call_center_untouched(): void
    {
        $label = fn (User $u) => collect(Sneakers::nav($u))->flatten(1)->pluck('label')->all();
        $seller = $this->user('sneaker_operator', 'Продавец');
        $this->assertSame(['Продажа', 'Продажи за день', 'Скан', 'Склад', 'Приёмка', 'Продажи · канбан', 'Дела', 'Помощь'], $label($seller));   // без закупок, аналитики, сводки
        $owner = $this->user('sneaker_owner', 'Владелец');
        $this->assertSame(['Продажи за день', 'Сводка', 'Помощь'], $label($owner));
        $this->assertFalse(Sneakers::seesCost($seller));
        $this->assertTrue(Sneakers::seesCost($owner));

        // колл-центр: «Пользователи» — в своём каркасе «Nordika», кроссовочный не подключается
        $ceil = Account::create(['name' => 'Потолки (тест)'])->id;
        $admin = $this->user('admin', 'Админ Потолков', $ceil);
        $html = $this->actingAs($admin)->get(route('settings.users.index'))->assertOk()->getContent();
        $this->assertStringContainsString('nd-html', $html);
        $this->assertStringNotContainsString('ns-html', $html);
        $this->assertStringNotContainsString('nordika-sneakers', $html);
    }
}
