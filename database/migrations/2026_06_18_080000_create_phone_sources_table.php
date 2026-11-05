<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->unique();   // 7XXXXXXXXXX
            $table->string('label', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('sort_order');
        });

        // Сид из прежнего хардкода INCOMING_PHONE_SOURCE_LABELS.
        $seed = [
            '79225090014' => 'вк',
            '79225090052' => 'директ',
            '79225150259' => 'авито частник',
            '79225070404' => 'радио',
            '79225085574' => 'вк',
            '79221797710' => 'авито',
            '79225174552' => 'сайт (директ)',
        ];

        $now = now();
        $sort = 10;
        $rows = [];
        foreach ($seed as $number => $label) {
            $rows[] = [
                'number' => $number,
                'label' => $label,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $sort += 10;
        }

        DB::table('phone_sources')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_sources');
    }
};
