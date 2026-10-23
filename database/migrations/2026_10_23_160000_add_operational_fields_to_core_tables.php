<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->string('registration_number', 60)->nullable()->after('name');
            $table->string('address')->nullable()->after('location');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('contact_name', 120)->nullable()->after('manager_id');
            $table->string('contact_phone', 30)->nullable()->after('contact_name');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->text('description')->nullable()->after('contact_email');
        });

        Schema::table('production_units', function (Blueprint $table) {
            $table->decimal('length_m', 8, 2)->nullable()->after('area_m2');
            $table->decimal('width_m', 8, 2)->nullable()->after('length_m');
            $table->string('soil_type', 60)->nullable()->after('width_m');
            $table->string('irrigation_type', 60)->nullable()->after('soil_type');
            $table->string('sun_exposure', 40)->nullable()->after('irrigation_type');
            $table->string('slope', 40)->nullable()->after('sun_exposure');
            $table->decimal('latitude', 10, 7)->nullable()->after('slope');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->text('notes')->nullable()->after('longitude');
        });

        Schema::table('crops', function (Blueprint $table) {
            $table->string('scientific_name', 150)->nullable()->after('name');
            $table->string('family', 100)->nullable()->after('scientific_name');
            $table->decimal('planting_density', 10, 2)->nullable()->after('estimated_cycle_days');
            $table->decimal('average_yield_kg_m2', 10, 3)->nullable()->after('planting_density');
            $table->decimal('seed_rate_kg_m2', 10, 4)->nullable()->after('average_yield_kg_m2');
            $table->decimal('default_sale_price', 14, 2)->nullable()->after('seed_rate_kg_m2');
            $table->text('description')->nullable()->after('default_sale_price');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->decimal('planting_density', 10, 2)->nullable()->after('cultivated_area_m2');
            $table->decimal('expected_yield_kg', 14, 3)->nullable()->after('planting_density');
            $table->decimal('actual_yield_kg', 14, 3)->nullable()->after('expected_yield_kg');
            $table->decimal('planned_budget', 16, 2)->nullable()->after('actual_yield_kg');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->decimal('duration_hours', 8, 2)->nullable()->after('performed_at');
            $table->unsignedInteger('workers_count')->nullable()->after('duration_hours');
            $table->decimal('actual_cost', 16, 2)->nullable()->after('estimated_cost');
            $table->string('weather_conditions', 100)->nullable()->after('actual_cost');
        });

        Schema::table('harvests', function (Blueprint $table) {
            $table->string('harvest_code', 60)->nullable()->after('campaign_id');
            $table->decimal('harvested_area_m2', 14, 2)->nullable()->after('quantity');
            $table->decimal('loss_quantity', 14, 3)->default(0)->after('harvested_area_m2');
            $table->string('grade', 40)->nullable()->after('quality');
        });
    }

    public function down(): void
    {
        Schema::table('harvests', function (Blueprint $table) {
            $table->dropColumn(['harvest_code', 'harvested_area_m2', 'loss_quantity', 'grade']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['duration_hours', 'workers_count', 'actual_cost', 'weather_conditions']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['planting_density', 'expected_yield_kg', 'actual_yield_kg', 'planned_budget']);
        });

        Schema::table('crops', function (Blueprint $table) {
            $table->dropColumn(['scientific_name', 'family', 'planting_density', 'average_yield_kg_m2', 'seed_rate_kg_m2', 'default_sale_price', 'description']);
        });

        Schema::table('production_units', function (Blueprint $table) {
            $table->dropColumn(['length_m', 'width_m', 'soil_type', 'irrigation_type', 'sun_exposure', 'slope', 'latitude', 'longitude', 'notes']);
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->dropColumn(['registration_number', 'address', 'latitude', 'longitude', 'contact_name', 'contact_phone', 'contact_email', 'description']);
        });
    }
};
