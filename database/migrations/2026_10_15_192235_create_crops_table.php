<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->string('name', 100);
            $table->string('variety', 100)->default('non_precisee');
            $table->string('production_unit', 10)->default('kg');
            $table->unsignedInteger('estimated_cycle_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['farm_id', 'name', 'variety']);
            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'is_active']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE crops ADD CONSTRAINT crops_cycle_days_positive CHECK (estimated_cycle_days IS NULL OR estimated_cycle_days > 0)');
            DB::statement("ALTER TABLE crops ADD CONSTRAINT crops_production_unit_kg CHECK (production_unit = 'kg')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crops');
    }
};
