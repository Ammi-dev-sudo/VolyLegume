<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('client_reference', 64)->nullable()->after('farm_id');
            $table->unique(['farm_id', 'client_reference'], 'activities_farm_client_reference_unique');
        });

        Schema::table('harvests', function (Blueprint $table) {
            $table->string('client_reference', 64)->nullable()->after('farm_id');
            $table->unique(['farm_id', 'client_reference'], 'harvests_farm_client_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique('activities_farm_client_reference_unique');
            $table->dropColumn('client_reference');
        });

        Schema::table('harvests', function (Blueprint $table) {
            $table->dropUnique('harvests_farm_client_reference_unique');
            $table->dropColumn('client_reference');
        });
    }
};
