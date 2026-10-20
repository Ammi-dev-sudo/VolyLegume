<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('campaign_id');
            $table->timestamp('harvested_at');
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 10)->default('kg');
            $table->string('quality', 50)->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'campaign_id', 'harvested_at']);

            $table->foreign(['farm_id', 'campaign_id'])
                ->references(['farm_id', 'id'])->on('campaigns');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE harvests ADD CONSTRAINT harvests_quantity_positive CHECK (quantity > 0)');
            DB::statement("ALTER TABLE harvests ADD CONSTRAINT harvests_unit_kg CHECK (unit = 'kg')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('harvests');
    }
};
