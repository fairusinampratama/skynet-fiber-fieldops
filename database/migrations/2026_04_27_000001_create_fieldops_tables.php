<?php

use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'code']);
        });

        Schema::create('submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->string('odc_box_id');
            $table->string('odc_photo_path')->nullable();
            $table->decimal('odc_latitude', 11, 8);
            $table->decimal('odc_longitude', 11, 8);
            $table->string('odp_box_id');
            $table->string('odp_photo_path')->nullable();
            $table->decimal('odp_latitude', 11, 8);
            $table->decimal('odp_longitude', 11, 8);
            $table->string('odp_core_color');
            $table->text('notes')->nullable();
            $table->string('status')->default(SubmissionStatus::Draft->value)->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['technician_id', 'status']);
        });

        Schema::create('submission_ports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type');
            $table->unsignedTinyInteger('port_number');
            $table->string('status');
            $table->unique(['submission_id', 'asset_type', 'port_number']);
        });

        Schema::create('odc_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('box_id');
            $table->string('photo_path')->nullable();
            $table->decimal('latitude', 11, 8);
            $table->decimal('longitude', 11, 8);
            $table->foreignId('source_submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['project_id', 'box_id']);
        });

        Schema::create('odp_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->foreignId('odc_asset_id')->nullable()->constrained('odc_assets')->nullOnDelete();
            $table->string('box_id');
            $table->string('photo_path')->nullable();
            $table->decimal('latitude', 11, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('core_color');
            $table->foreignId('source_submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['project_id', 'box_id']);
        });

        Schema::create('odc_ports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('odc_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('port_number');
            $table->string('status');
            $table->foreignId('source_submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['odc_asset_id', 'port_number']);
        });

        Schema::create('odp_ports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('odp_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('port_number');
            $table->string('status');
            $table->foreignId('source_submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['odp_asset_id', 'port_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odp_ports');
        Schema::dropIfExists('odc_ports');
        Schema::dropIfExists('odp_assets');
        Schema::dropIfExists('odc_assets');
        Schema::dropIfExists('submission_ports');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('projects');
    }
};
