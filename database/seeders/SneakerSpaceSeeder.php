<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\PurchaseStage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Второе пространство — кроссовочный отдел (отдельный Account, полностью изолирован).
 *
 * Идемпотентен (firstOrCreate). В контексте seeder нет авторизованного пользователя,
 * поэтому account_id у всех сущностей задаётся явно (глобальный скоуп BelongsToAccount
 * без auth-пользователя не срабатывает).
 */
class SneakerSpaceSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::firstOrCreate(['name' => 'Кроссовки'], []);

        // Сотрудники отдела
        User::firstOrCreate(
            ['email' => 'sneaker', 'account_id' => $account->id],
            [
                'name' => 'Руководитель кроссовок',
                'password' => Hash::make('password'),
                'role' => 'sneaker_head',
                'is_active' => 1,
            ]
        );
        User::firstOrCreate(
            ['email' => 'sneaker_op', 'account_id' => $account->id],
            [
                'name' => 'Оператор кроссовок',
                'password' => Hash::make('password'),
                'role' => 'sneaker_operator',
                'is_active' => 1,
            ]
        );

        // Канбан №2 — воронка продаж (движок Сделок)
        $pipeline = Pipeline::firstOrCreate(
            ['account_id' => $account->id, 'name' => 'Продажи кроссовок', 'is_default' => 1],
            []
        );

        $salesStages = [
            ['name' => 'Новый лид', 'sort' => 10],
            ['name' => 'Связались / В работе', 'sort' => 20],
            ['name' => 'Подобрали модель', 'sort' => 30],
            ['name' => 'Бронь / Счёт', 'sort' => 40],
            ['name' => 'Продано', 'sort' => 50, 'is_final' => 1],
            ['name' => 'Отказ', 'sort' => 60],
        ];
        foreach ($salesStages as $s) {
            PipelineStage::firstOrCreate(
                ['account_id' => $account->id, 'pipeline_id' => $pipeline->id, 'name' => $s['name']],
                ['sort' => $s['sort'], 'is_final' => $s['is_final'] ?? 0]
            );
        }

        // Канбан №1 — закупки (новая сущность)
        $purchaseStages = [
            ['name' => 'Надо купить', 'sort' => 10],
            ['name' => 'Заказано', 'sort' => 20],
            ['name' => 'Оплачено', 'sort' => 30],
            ['name' => 'В пути', 'sort' => 40],
            ['name' => 'Получено / На складе', 'sort' => 50],
            ['name' => 'Готово к продаже', 'sort' => 60, 'is_final' => 1],
        ];
        foreach ($purchaseStages as $s) {
            PurchaseStage::firstOrCreate(
                ['account_id' => $account->id, 'name' => $s['name']],
                ['sort' => $s['sort'], 'is_final' => $s['is_final'] ?? 0]
            );
        }
    }
}
