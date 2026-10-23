<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_id')->unique()->constrained('crops')->cascadeOnDelete();
            $table->text('overview')->nullable();
            $table->text('varieties')->nullable();
            $table->text('climate')->nullable();
            $table->text('soil')->nullable();
            $table->text('land_preparation')->nullable();
            $table->text('planting_material')->nullable();
            $table->text('nursery')->nullable();
            $table->text('sowing')->nullable();
            $table->text('transplanting')->nullable();
            $table->text('irrigation')->nullable();
            $table->text('fertilization')->nullable();
            $table->text('maintenance')->nullable();
            $table->text('pests_diseases')->nullable();
            $table->text('cycle_duration')->nullable();
            $table->text('harvest')->nullable();
            $table->text('expected_yield')->nullable();
            $table->text('storage')->nullable();
            $table->text('sources')->nullable();
            $table->json('common_steps')->nullable();
            $table->json('category_steps')->nullable();
            $table->json('specific_steps')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_guides');
    }
};
