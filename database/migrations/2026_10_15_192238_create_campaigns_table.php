<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('production_unit_id');
            $table->unsignedBigInteger('crop_id');
            $table->string('name', 150);
            $table->date('start_date');
            $table->string('reference_type', 20);
            $table->date('expected_end_date');
            $table->date('actual_end_date')->nullable();
            $table->decimal('cultivated_area_m2', 14, 2);
            $table->string('status', 20)->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'status', 'start_date']);
            $table->index(['production_unit_id', 'start_date', 'expected_end_date']);
            $table->index('crop_id');

            $table->foreign(['farm_id', 'production_unit_id'])
                ->references(['farm_id', 'id'])->on('production_units');
            $table->foreign(['farm_id', 'crop_id'])
                ->references(['farm_id', 'id'])->on('crops');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_cultivated_area_positive CHECK (cultivated_area_m2 > 0)');
            DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_expected_end_after_start CHECK (expected_end_date >= start_date)');
            DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_actual_end_after_start CHECK (actual_end_date IS NULL OR actual_end_date >= start_date)');
            DB::statement("ALTER TABLE campaigns ADD CONSTRAINT campaigns_status_valid CHECK (status IN ('planned', 'ongoing', 'suspended', 'completed', 'cancelled'))");
            DB::statement("ALTER TABLE campaigns ADD CONSTRAINT campaigns_reference_type_valid CHECK (reference_type IN ('sowing', 'planting'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
