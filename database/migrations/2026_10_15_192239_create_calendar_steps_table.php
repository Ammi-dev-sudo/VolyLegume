<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('crop_id');
            $table->string('name', 120);
            $table->string('activity_type', 30);
            $table->integer('offset_days');
            $table->unsignedInteger('position');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['crop_id', 'position']);
            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'crop_id', 'is_active']);

            $table->foreign(['farm_id', 'crop_id'])
                ->references(['farm_id', 'id'])->on('crops');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE calendar_steps ADD CONSTRAINT calendar_steps_position_positive CHECK (position >= 1)');
            DB::statement("ALTER TABLE calendar_steps ADD CONSTRAINT calendar_steps_activity_type_valid CHECK (activity_type IN ('soil_preparation', 'sowing', 'transplanting', 'fertilization', 'treatment', 'irrigation', 'weeding', 'harvest', 'observation', 'maintenance'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_steps');
    }
};
