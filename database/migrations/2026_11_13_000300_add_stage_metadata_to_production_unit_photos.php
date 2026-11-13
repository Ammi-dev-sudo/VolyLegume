<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_unit_photos', function (Blueprint $table) {
            $table->string('growth_stage', 30)->nullable()->after('caption');
            $table->string('observation_type', 30)->nullable()->after('growth_stage');
            $table->boolean('is_demo')->default(false)->after('observation_type');
        });
    }

    public function down(): void
    {
        Schema::table('production_unit_photos', function (Blueprint $table) {
            $table->dropColumn(['growth_stage', 'observation_type', 'is_demo']);
        });
    }
};
