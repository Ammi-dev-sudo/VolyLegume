<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('calendar_step_id')->nullable();
            $table->string('type', 30);
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('planned_date');
            $table->timestamp('performed_at')->nullable();
            $table->string('status', 20)->default('planned');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->decimal('estimated_cost', 16, 2)->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'calendar_step_id']);
            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'planned_date']);
            $table->index(['farm_id', 'campaign_id', 'planned_date']);
            $table->index(['farm_id', 'type', 'planned_date']);
            $table->index(['assigned_to', 'status', 'planned_date']);

            $table->foreign(['farm_id', 'campaign_id'])
                ->references(['farm_id', 'id'])->on('campaigns');
            $table->foreign(['farm_id', 'calendar_step_id'])
                ->references(['farm_id', 'id'])->on('calendar_steps');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_estimated_cost_non_negative CHECK (estimated_cost IS NULL OR estimated_cost >= 0)');
            DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_type_valid CHECK (type IN ('soil_preparation', 'sowing', 'transplanting', 'fertilization', 'treatment', 'irrigation', 'weeding', 'harvest', 'observation', 'maintenance'))");
            DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_status_valid CHECK (status IN ('planned', 'completed', 'cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
