<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->cascadeOnDelete();
            $table->string('model', 50);
            $table->nullableMorphs('subject');
            $table->string('label', 120)->nullable();
            $table->decimal('value', 16, 4)->nullable();
            $table->decimal('score', 8, 4)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['model', 'subject_type', 'subject_id']);
            $table->index(['farm_id', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
