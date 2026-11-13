<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_stage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('campaign_id');
            $table->string('stage', 30);
            $table->timestamp('recorded_at');
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['farm_id', 'id']);
            $table->unique(['campaign_id', 'stage']);
            $table->index(['farm_id', 'campaign_id', 'recorded_at']);

            $table->foreign(['farm_id', 'campaign_id'])
                ->references(['farm_id', 'id'])->on('campaigns');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE campaign_stage_events ADD CONSTRAINT campaign_stage_events_stage_valid CHECK (stage IN ('soil_preparation', 'sowing', 'germination', 'growth', 'transplanting', 'maintenance', 'flowering', 'harvest', 'completed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_stage_events');
    }
};
