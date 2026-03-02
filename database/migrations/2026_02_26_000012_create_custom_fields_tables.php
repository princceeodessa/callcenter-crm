<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('custom_field_groups', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->string('entity_type', 20); // deal/contact
      $table->string('name');
      $table->integer('sort')->default(100);
      $table->timestamps();
      $table->index(['account_id','entity_type','sort']);
    });

    Schema::create('custom_fields', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->string('entity_type', 20);
      $table->foreignId('group_id')->nullable()->constrained('custom_field_groups')->nullOnDelete();
      $table->string('code', 100);
      $table->string('name');
      $table->string('field_type', 30);
      $table->boolean('is_required')->default(false);
      $table->integer('sort')->default(100);
      $table->json('meta')->nullable();
      $table->timestamps();

      $table->unique(['account_id','entity_type','code']);
      $table->index(['group_id','sort']);
    });

    Schema::create('custom_field_options', function (Blueprint $table) {
      $table->id();
      $table->foreignId('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();
      $table->string('value');
      $table->integer('sort')->default(100);
      $table->index(['custom_field_id','sort']);
    });

    Schema::create('custom_field_values', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->string('entity_type', 20);
      $table->unsignedBigInteger('entity_id');
      $table->foreignId('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();

      $table->text('value_text')->nullable();
      $table->decimal('value_number', 14, 4)->nullable();
      $table->date('value_date')->nullable();
      $table->dateTime('value_datetime')->nullable();
      $table->json('value_json')->nullable();

      $table->timestamps();

      $table->unique(['account_id','entity_type','entity_id','custom_field_id'], 'uq_cfv');
      $table->index(['account_id','entity_type','entity_id'], 'idx_cfv_entity');
    });
  }

  public function down(): void {
    Schema::dropIfExists('custom_field_values');
    Schema::dropIfExists('custom_field_options');
    Schema::dropIfExists('custom_fields');
    Schema::dropIfExists('custom_field_groups');
  }
};
