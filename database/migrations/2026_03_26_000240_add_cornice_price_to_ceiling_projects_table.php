<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->decimal('cornice_price_per_m', 12, 2)->default(0)->after('curtain_niche_price');
        });
    }

    public function down(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->dropColumn('cornice_price_per_m');
        });
    }
};
