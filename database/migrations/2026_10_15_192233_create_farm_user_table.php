<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->foreignId('user_id')->constrained('users');
            $table->string('role', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['farm_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
            $table->index(['farm_id', 'role', 'is_active']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE farm_user ADD CONSTRAINT farm_user_role_valid CHECK (role IN ('admin', 'responsable', 'agent'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_user');
    }
};
