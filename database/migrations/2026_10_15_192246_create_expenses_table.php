<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('category', 30);
            $table->string('description');
            $table->decimal('amount', 16, 2);
            $table->date('expense_date');
            $table->string('reference', 100)->nullable();
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['farm_id', 'reference']);
            $table->unique('stock_movement_id');
            $table->index(['farm_id', 'expense_date']);
            $table->index(['farm_id', 'campaign_id', 'expense_date']);

            $table->foreign(['farm_id', 'campaign_id'])
                ->references(['farm_id', 'id'])->on('campaigns');
            $table->foreign(['farm_id', 'stock_movement_id'])
                ->references(['farm_id', 'id'])->on('stock_movements');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_positive CHECK (amount > 0)');
            DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_category_valid CHECK (category IN ('inputs', 'labour', 'treatment', 'transport', 'energy', 'other'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
