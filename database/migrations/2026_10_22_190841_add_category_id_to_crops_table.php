<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crops', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('farm_id')
                ->constrained('crop_categories')
                ->nullOnDelete();

            $table->index(['farm_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('crops', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['farm_id', 'category_id']);
            $table->dropColumn('category_id');
        });
    }
};
