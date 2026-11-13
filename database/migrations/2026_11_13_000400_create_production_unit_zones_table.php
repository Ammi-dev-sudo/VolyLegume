<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_unit_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->foreignId('production_unit_id')->constrained('production_units')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->decimal('area_m2', 14, 2);
            $table->string('soil_type', 60)->nullable();
            $table->string('irrigation_type', 60)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['farm_id', 'id']);
            $table->unique(['production_unit_id', 'code']);
            $table->index(['farm_id', 'production_unit_id', 'is_active']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('production_unit_zone_id')->nullable()->after('production_unit_id')
                ->constrained('production_unit_zones')->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE production_unit_zones ADD CONSTRAINT production_unit_zones_area_positive CHECK (area_m2 > 0)');
        }
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_unit_zone_id');
        });

        Schema::dropIfExists('production_unit_zones');
    }
};
