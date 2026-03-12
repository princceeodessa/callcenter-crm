<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::firstOrCreate(
            ['name' => 'Default Account'],
            []
        );

        User::firstOrCreate(
            ['email' => 'admin', 'account_id' => $account->id],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => 1,
            ]
        );

        $pipeline = Pipeline::firstOrCreate(
            ['account_id' => $account->id, 'name' => "\u{041A}\u{043E}\u{043B}\u{043B}-\u{0446}\u{0435}\u{043D}\u{0442}\u{0440}", 'is_default' => 1],
            []
        );

        $stages = [
            ['name' => "\u{041F}\u{043E}\u{0441}\u{0442}\u{0443}\u{043F}\u{0438}\u{043B} \u{043B}\u{0438}\u{0434} / \u{041D}\u{0430}\u{0437}\u{043D}\u{0430}\u{0447}\u{0438}\u{0442}\u{044C} \u{043E}\u{0442}\u{0432}\u{0435}\u{0442}\u{0441}\u{0442}\u{0432}\u{0435}\u{043D}\u{043D}\u{043E}\u{0433}\u{043E}", 'sort' => 10],
            ['name' => "\u{0412}\u{0437}\u{044F}\u{043B}\u{0438} \u{0432} \u{0440}\u{0430}\u{0431}\u{043E}\u{0442}\u{0443}/\u{0421}\u{0432}\u{044F}\u{0437}\u{044B}\u{0432}\u{0430}\u{0435}\u{043C}\u{0441}\u{044F}", 'sort' => 20],
            ['name' => "\u{041A}\u{043B}\u{0438}\u{0435}\u{043D}\u{0442} \u{043A}\u{0432}\u{0430}\u{043B}\u{0438}\u{0444}./\u{0414}\u{043E}\u{0436}\u{0430}\u{0442}\u{044C} \u{0434}\u{043E} \u{0437}\u{0430}\u{043C}\u{0435}\u{0440}\u{0430}", 'sort' => 30],
            ['name' => "\u{0417}\u{0430}\u{043C}\u{0435}\u{0440} \u{043D}\u{0430}\u{0437}\u{043D}\u{0430}\u{0447}\u{0435}\u{043D}", 'sort' => 40],
            ['name' => "\u{0417}\u{0430}\u{0432}\u{0435}\u{0440}\u{0448}\u{0438}\u{0442}\u{044C} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0443}", 'sort' => 60, 'is_final' => 1],
        ];

        foreach ($stages as $stage) {
            PipelineStage::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'pipeline_id' => $pipeline->id,
                    'name' => $stage['name'],
                ],
                [
                    'sort' => $stage['sort'],
                    'is_final' => $stage['is_final'] ?? 0,
                ]
            );
        }

        Tag::firstOrCreate(
            ['account_id' => $account->id, 'name' => 'BitrixGPT'],
            []
        );
    }
}
