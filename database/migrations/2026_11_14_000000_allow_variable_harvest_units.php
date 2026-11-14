<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE harvests DROP CONSTRAINT IF EXISTS harvests_unit_kg');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE harvests ADD CONSTRAINT harvests_unit_kg CHECK (unit = 'kg')");
        }
    }
};
