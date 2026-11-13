<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phytosanitary_treatments', function (Blueprint $table) {
            $table->unsignedBigInteger('crop_issue_id')->nullable()->after('campaign_id');
            $table->text('result_observed')->nullable()->after('notes');
            $table->index('crop_issue_id');

            $table->foreign('crop_issue_id')->references('id')->on('crop_issues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('phytosanitary_treatments', function (Blueprint $table) {
            $table->dropForeign(['crop_issue_id']);
            $table->dropColumn(['crop_issue_id', 'result_observed']);
        });
    }
};
