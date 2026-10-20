<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->string('code', 30);
            $table->string('name', 120);
            $table->decimal('area_m2', 14, 2);
            $table->string('location');
            $table->string('type', 20)->default('plot');
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->unique(['farm_id', 'code']);
            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'status']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE production_units ADD CONSTRAINT production_units_area_m2_positive CHECK (area_m2 > 0)');
            DB::statement("ALTER TABLE production_units ADD CONSTRAINT production_units_type_valid CHECK (type = 'plot')");
            DB::statement("ALTER TABLE production_units ADD CONSTRAINT production_units_status_valid CHECK (status IN ('available', 'occupied', 'resting', 'inactive'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_units');
    }
};
