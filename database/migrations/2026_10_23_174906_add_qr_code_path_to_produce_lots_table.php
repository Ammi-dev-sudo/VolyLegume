<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produce_lots', function (Blueprint $table) {
            $table->string('qr_code_path')->nullable()->after('notes');
            $table->string('trace_token', 64)->nullable()->unique()->after('qr_code_path');
        });
    }

    public function down(): void
    {
        Schema::table('produce_lots', function (Blueprint $table) {
            $table->dropColumn(['qr_code_path', 'trace_token']);
        });
    }
};
