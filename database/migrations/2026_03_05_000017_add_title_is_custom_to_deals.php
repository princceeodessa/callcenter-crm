<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->boolean('title_is_custom')->default(false)->after('title');
        });

        // Mark existing deals as "custom" if they don't look auto-generated.
        DB::table('deals')
            ->where('title', 'not like', 'Чат %')
            ->where('title', 'not like', 'Звонки:%')
            ->update(['title_is_custom' => 1]);
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('title_is_custom');
        });
    }
};
