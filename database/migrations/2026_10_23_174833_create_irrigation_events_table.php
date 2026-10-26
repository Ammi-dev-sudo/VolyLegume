<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irrigation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->foreignId('production_unit_id')->constrained('production_units');
            $table->foreignId('campaign_id')->constrained('campaigns');
            $table->date('irrigated_at');
            $table->decimal('duration_hours', 8, 2)->nullable();
            $table->decimal('volume_m3', 12, 3);
            $table->string('method', 60)->nullable();
            $table->string('source', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'campaign_id', 'irrigated_at']);
            $table->index(['farm_id', 'production_unit_id', 'irrigated_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE irrigation_events ADD CONSTRAINT irrigation_events_volume_positive CHECK (volume_m3 > 0)');
            DB::statement('ALTER TABLE irrigation_events ADD CONSTRAINT irrigation_events_duration_positive CHECK (duration_hours IS NULL OR duration_hours > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('irrigation_events');
    }
};
