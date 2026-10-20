<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('category', 30);
            $table->string('base_unit', 10);
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('minimum_stock', 14, 3)->default(0);
            $table->unsignedInteger('default_pre_harvest_days')->nullable();
            $table->string('delay_source')->nullable();
            $table->timestamp('critical_notified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['farm_id', 'code']);
            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'category', 'is_active']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inputs ADD CONSTRAINT inputs_quantity_non_negative CHECK (quantity >= 0)');
            DB::statement('ALTER TABLE inputs ADD CONSTRAINT inputs_minimum_stock_non_negative CHECK (minimum_stock >= 0)');
            DB::statement('ALTER TABLE inputs ADD CONSTRAINT inputs_pre_harvest_days_non_negative CHECK (default_pre_harvest_days IS NULL OR default_pre_harvest_days >= 0)');
            DB::statement("ALTER TABLE inputs ADD CONSTRAINT inputs_category_valid CHECK (category IN ('seed', 'fertilizer', 'phytosanitary', 'fuel', 'consumable'))");
            DB::statement("ALTER TABLE inputs ADD CONSTRAINT inputs_base_unit_valid CHECK (base_unit IN ('kg', 'L', 'piece'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inputs');
    }
};
