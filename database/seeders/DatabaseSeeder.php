<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Account;
use App\Models\User;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tag;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Account
        $account = Account::firstOrCreate(
            ['name' => 'Default Account'],
            []
        );

        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com', 'account_id' => $account->id],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => 1,
            ]
        );

        // Default pipeline
        $pipeline = Pipeline::firstOrCreate(
            ['account_id' => $account->id, 'name' => 'Колл-центр', 'is_default' => 1],
            []
        );

        // Stages (как на скринах)
        $stages = [
            ['name' => 'Поступил лид / Назначить ответственного', 'sort' => 10],
            ['name' => 'Взяли в работу/Связываемся', 'sort' => 20],
            ['name' => 'Клиент квалиф./Дожать до замера', 'sort' => 30],
            ['name' => 'Замер назначен', 'sort' => 40],
            ['name' => 'Замер прошел/Дожать до договора', 'sort' => 50],
            ['name' => 'Завершить сделку', 'sort' => 60, 'is_final' => 1],
        ];

        foreach ($stages as $s) {
            PipelineStage::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'pipeline_id' => $pipeline->id,
                    'name' => $s['name'],
                ],
                [
                    'sort' => $s['sort'],
                    'is_final' => $s['is_final'] ?? 0,
                ]
            );
        }

        // Default tag like "BitrixGPT"
        Tag::firstOrCreate(
            ['account_id' => $account->id, 'name' => 'BitrixGPT'],
            []
        );
    }
}