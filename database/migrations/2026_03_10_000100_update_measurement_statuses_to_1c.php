<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('measurements')->where('status', 'confirmed')->update(['status' => 'accepted']);
        DB::table('measurements')->where('status', 'done')->update(['status' => 'concluded']);
        DB::table('measurements')->where('status', 'refused_after_measurement')->update(['status' => 'not_concluded']);
    }

    public function down(): void
    {
        DB::table('measurements')->where('status', 'accepted')->update(['status' => 'confirmed']);
        DB::table('measurements')->where('status', 'concluded')->update(['status' => 'done']);
        DB::table('measurements')->where('status', 'not_concluded')->update(['status' => 'refused_after_measurement']);
    }
};
