<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phytosanitary_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('input_id');
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('stock_movement_id');
            $table->timestamp('applied_at');
            $table->decimal('dose_value', 14, 3);
            $table->string('dose_unit', 30);
            $table->unsignedInteger('pre_harvest_days');
            $table->string('delay_source_snapshot');
            $table->timestamp('minimum_harvest_at');
            $table->foreignId('applied_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('activity_id');
            $table->unique('stock_movement_id');
            $table->index(['farm_id', 'campaign_id', 'applied_at']);
            $table->index(['farm_id', 'campaign_id', 'minimum_harvest_at']);
            $table->index('input_id');

            $table->foreign(['farm_id', 'campaign_id'])
                ->references(['farm_id', 'id'])->on('campaigns');
            $table->foreign(['farm_id', 'input_id'])
                ->references(['farm_id', 'id'])->on('inputs');
            $table->foreign(['farm_id', 'activity_id'])
                ->references(['farm_id', 'id'])->on('activities');
            $table->foreign(['farm_id', 'stock_movement_id'])
                ->references(['farm_id', 'id'])->on('stock_movements');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE phytosanitary_treatments ADD CONSTRAINT treatments_dose_positive CHECK (dose_value > 0)');
            DB::statement('ALTER TABLE phytosanitary_treatments ADD CONSTRAINT treatments_pre_harvest_days_non_negative CHECK (pre_harvest_days >= 0)');
            DB::statement('ALTER TABLE phytosanitary_treatments ADD CONSTRAINT treatments_minimum_harvest_after_applied CHECK (minimum_harvest_at >= applied_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('phytosanitary_treatments');
    }
};
