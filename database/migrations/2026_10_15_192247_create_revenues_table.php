<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('harvest_id')->nullable();
            $table->string('category', 30);
            $table->string('description');
            $table->decimal('amount', 16, 2);
            $table->date('revenue_date');
            $table->string('reference', 100)->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['farm_id', 'reference']);
            $table->index(['farm_id', 'revenue_date']);
            $table->index(['farm_id', 'campaign_id', 'revenue_date']);
            $table->index('harvest_id');

            $table->foreign(['farm_id', 'campaign_id'])
                ->references(['farm_id', 'id'])->on('campaigns');
            $table->foreign(['farm_id', 'harvest_id'])
                ->references(['farm_id', 'id'])->on('harvests');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE revenues ADD CONSTRAINT revenues_amount_positive CHECK (amount > 0)');
            DB::statement("ALTER TABLE revenues ADD CONSTRAINT revenues_category_valid CHECK (category IN ('harvest_sale', 'other'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('revenues');
    }
};
