<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->unsignedBigInteger('production_unit_id');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('reference', 40);
            $table->string('type', 30);
            $table->string('title', 150);
            $table->text('description');
            $table->date('observed_at');
            $table->string('severity', 20)->default('moderate');
            $table->decimal('affected_area_m2', 14, 2)->nullable();
            $table->string('status', 20)->default('open');
            $table->text('recommended_actions')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['farm_id', 'reference']);
            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'status', 'severity']);
            $table->index(['farm_id', 'production_unit_id', 'observed_at']);
            $table->index(['farm_id', 'observed_at']);

            $table->foreign(['farm_id', 'production_unit_id'])
                ->references(['farm_id', 'id'])->on('production_units');
            $table->foreign(['farm_id', 'campaign_id'])
                ->references(['farm_id', 'id'])->on('campaigns');
        });

        Schema::create('crop_issue_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->foreignId('crop_issue_id')->constrained('crop_issues')->cascadeOnDelete();
            $table->string('photo_path');
            $table->string('kind', 20)->default('observation');
            $table->string('caption')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'crop_issue_id', 'taken_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE crop_issues ADD CONSTRAINT crop_issues_type_valid CHECK (type IN ('disease', 'pest', 'fungus', 'nutrient_deficiency', 'irrigation', 'drought', 'excess_water', 'poor_growth', 'soil_degradation', 'other'))");
            DB::statement("ALTER TABLE crop_issues ADD CONSTRAINT crop_issues_severity_valid CHECK (severity IN ('low', 'moderate', 'high', 'critical'))");
            DB::statement("ALTER TABLE crop_issues ADD CONSTRAINT crop_issues_status_valid CHECK (status IN ('open', 'in_progress', 'monitoring', 'resolved'))");
            DB::statement('ALTER TABLE crop_issues ADD CONSTRAINT crop_issues_affected_area_non_negative CHECK (affected_area_m2 IS NULL OR affected_area_m2 >= 0)');
            DB::statement("ALTER TABLE crop_issue_photos ADD CONSTRAINT crop_issue_photos_kind_valid CHECK (kind IN ('observation', 'before', 'treatment', 'after'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_issue_photos');
        Schema::dropIfExists('crop_issues');
    }
};
