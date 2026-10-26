<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ставки руководства для строк договоров, расход которых CRM БлагоДар не посчитала (работа без ставки монтажникам,
// товар без цены закупки в 1С): сводка владельца досчитывает по ним прибыль у нас (App\Services\Owner\ContractProfitFill).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_cost_rates', function (Blueprint $table) {
            $table->id();
            // sha1(вид, название, единица) — название из 1С бывает длинным для уникального индекса
            $table->char('key', 40)->unique();
            $table->string('kind', 10);                 // work | goods
            $table->string('name', 500);
            $table->string('unit', 50)->default('');
            $table->string('mode', 10)->default('unit'); // unit — ₽ за единицу строки, share — % от её суммы
            $table->decimal('value', 12, 2);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_cost_rates');
    }
};
