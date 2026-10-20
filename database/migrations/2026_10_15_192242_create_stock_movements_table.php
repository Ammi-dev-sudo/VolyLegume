<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('input_id');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->string('type', 20);
            $table->decimal('quantity_delta', 14, 3);
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->constrained('users');
            $table->string('reason');
            $table->unsignedBigInteger('corrects_movement_id')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'input_id', 'occurred_at']);
            $table->index(['farm_id', 'campaign_id', 'occurred_at']);
            $table->index('activity_id');
            $table->index('corrects_movement_id');

            $table->foreign(['farm_id', 'input_id'])
                ->references(['farm_id', 'id'])->on('inputs');
            $table->foreign(['farm_id', 'campaign_id'])
                ->references(['farm_id', 'id'])->on('campaigns');
            $table->foreign(['farm_id', 'activity_id'])
                ->references(['farm_id', 'id'])->on('activities');
            $table->foreign(['farm_id', 'corrects_movement_id'])
                ->references(['farm_id', 'id'])->on('stock_movements');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_delta_non_zero CHECK (quantity_delta <> 0)');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_valid CHECK (type IN ('initial', 'entry', 'exit', 'consumption', 'adjustment'))");
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_delta_sign CHECK (
                (type IN ('initial', 'entry') AND quantity_delta > 0)
                OR (type IN ('exit', 'consumption') AND quantity_delta < 0)
                OR (type = 'adjustment')
            )");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
