<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('pipeline_stages')
            ->where(function ($query) {
                $query->whereRaw('LOWER(name) = ?', ["\u{0441}\u{043F}\u{0430}\u{043C}"])
                    ->orWhereRaw('LOWER(name) = ?', ['spam']);
            })
            ->update([
                'name' => "\u{041D}\u{0435}\u{0446}\u{0435}\u{043B}\u{0435}\u{0432}\u{043E}\u{0435}",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('pipeline_stages')
            ->where('name', "\u{041D}\u{0435}\u{0446}\u{0435}\u{043B}\u{0435}\u{0432}\u{043E}\u{0435}")
            ->update([
                'name' => "\u{0421}\u{043F}\u{0430}\u{043C}",
                'updated_at' => now(),
            ]);
    }
};