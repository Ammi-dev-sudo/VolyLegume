<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('location');
            $table->string('type', 30)->default('market_gardening');
            $table->decimal('area_m2', 14, 2);
            $table->foreignId('manager_id')->nullable()->constrained('users');
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3)->default('MGA');
            $table->timestamps();

            $table->index('status');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE farms ADD CONSTRAINT farms_area_m2_positive CHECK (area_m2 > 0)');
            DB::statement("ALTER TABLE farms ADD CONSTRAINT farms_status_valid CHECK (status IN ('draft', 'active', 'inactive'))");
            DB::statement("ALTER TABLE farms ADD CONSTRAINT farms_currency_mga CHECK (currency = 'MGA')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
