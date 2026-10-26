<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->decimal('water_quota_m3', 12, 3)->nullable()->after('planned_budget');
            $table->decimal('water_alert_threshold_percent', 5, 2)->nullable()->default(80)->after('water_quota_m3');
            $table->text('water_notes')->nullable()->after('water_alert_threshold_percent');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_water_quota_positive CHECK (water_quota_m3 IS NULL OR water_quota_m3 >= 0)');
            DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_water_threshold_valid CHECK (water_alert_threshold_percent IS NULL OR (water_alert_threshold_percent >= 0 AND water_alert_threshold_percent <= 100))');
        }
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['water_quota_m3', 'water_alert_threshold_percent', 'water_notes']);
        });
    }
};
