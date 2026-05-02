<?php

use App\Enums\AssetType;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('asset_type')->nullable()->after('work_date')->index();
            $table->string('box_id')->nullable()->after('asset_type');
            $table->string('photo_path')->nullable()->after('box_id');
            $table->decimal('latitude', 11, 8)->nullable()->after('photo_path');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('core_color')->nullable()->after('longitude');
            $table->foreignId('parent_odc_asset_id')->nullable()->after('core_color')->constrained('odc_assets')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            $table->text('assignment_notes')->nullable()->after('assigned_at');
        });

        $this->splitLegacyCombinedSubmissions();

        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn([
                'odc_box_id',
                'odc_photo_path',
                'odc_latitude',
                'odc_longitude',
                'odp_box_id',
                'odp_photo_path',
                'odp_latitude',
                'odp_longitude',
                'odp_core_color',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('odc_box_id')->nullable();
            $table->string('odc_photo_path')->nullable();
            $table->decimal('odc_latitude', 11, 8)->nullable();
            $table->decimal('odc_longitude', 11, 8)->nullable();
            $table->string('odp_box_id')->nullable();
            $table->string('odp_photo_path')->nullable();
            $table->decimal('odp_latitude', 11, 8)->nullable();
            $table->decimal('odp_longitude', 11, 8)->nullable();
            $table->string('odp_core_color')->nullable();
        });

        DB::table('submissions')->orderBy('id')->chunkById(200, function ($submissions): void {
            foreach ($submissions as $submission) {
                if ($submission->asset_type === AssetType::Odc->value) {
                    DB::table('submissions')
                        ->where('id', $submission->id)
                        ->update([
                            'odc_box_id' => $submission->box_id,
                            'odc_photo_path' => $submission->photo_path,
                            'odc_latitude' => $submission->latitude,
                            'odc_longitude' => $submission->longitude,
                        ]);

                    continue;
                }

                DB::table('submissions')
                    ->where('id', $submission->id)
                    ->update([
                        'odp_box_id' => $submission->box_id,
                        'odp_photo_path' => $submission->photo_path,
                        'odp_latitude' => $submission->latitude,
                        'odp_longitude' => $submission->longitude,
                        'odp_core_color' => $submission->core_color,
                    ]);
            }
        });

        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_odc_asset_id');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn([
                'asset_type',
                'box_id',
                'photo_path',
                'latitude',
                'longitude',
                'core_color',
                'assigned_at',
                'assignment_notes',
            ]);
        });
    }

    private function splitLegacyCombinedSubmissions(): void
    {
        DB::table('submissions')
            ->orderBy('id')
            ->chunkById(100, function ($submissions): void {
                foreach ($submissions as $submission) {
                    if (filled($submission->asset_type)) {
                        continue;
                    }

                    $status = $submission->status === SubmissionStatus::Draft->value
                        ? SubmissionStatus::Assigned->value
                        : $submission->status;

                    DB::table('submissions')
                        ->where('id', $submission->id)
                        ->update([
                            'asset_type' => AssetType::Odc->value,
                            'box_id' => $submission->odc_box_id,
                            'photo_path' => $submission->odc_photo_path,
                            'latitude' => $submission->odc_latitude,
                            'longitude' => $submission->odc_longitude,
                            'status' => $status,
                            'assigned_by' => $submission->reviewed_by,
                            'assigned_at' => $submission->created_at,
                            'assignment_notes' => $submission->notes,
                        ]);

                    $odpSubmissionId = DB::table('submissions')->insertGetId([
                        'project_id' => $submission->project_id,
                        'technician_id' => $submission->technician_id,
                        'team_id' => $submission->team_id,
                        'area_id' => $submission->area_id,
                        'work_date' => $submission->work_date,
                        'odc_box_id' => $submission->odc_box_id,
                        'odc_photo_path' => $submission->odc_photo_path,
                        'odc_latitude' => $submission->odc_latitude,
                        'odc_longitude' => $submission->odc_longitude,
                        'odp_box_id' => $submission->odp_box_id,
                        'odp_photo_path' => $submission->odp_photo_path,
                        'odp_latitude' => $submission->odp_latitude,
                        'odp_longitude' => $submission->odp_longitude,
                        'odp_core_color' => $submission->odp_core_color,
                        'asset_type' => AssetType::Odp->value,
                        'box_id' => $submission->odp_box_id,
                        'photo_path' => $submission->odp_photo_path,
                        'latitude' => $submission->odp_latitude,
                        'longitude' => $submission->odp_longitude,
                        'core_color' => $submission->odp_core_color,
                        'parent_odc_asset_id' => DB::table('odc_assets')
                            ->where('source_submission_id', $submission->id)
                            ->value('id'),
                        'notes' => $submission->notes,
                        'status' => $status,
                        'review_notes' => $submission->review_notes,
                        'reviewed_by' => $submission->reviewed_by,
                        'assigned_by' => $submission->reviewed_by,
                        'assigned_at' => $submission->created_at,
                        'assignment_notes' => $submission->notes,
                        'submitted_at' => $submission->submitted_at,
                        'reviewed_at' => $submission->reviewed_at,
                        'created_at' => $submission->created_at,
                        'updated_at' => $submission->updated_at,
                    ]);

                    DB::table('submission_ports')
                        ->where('submission_id', $submission->id)
                        ->where('asset_type', AssetType::Odp->value)
                        ->update(['submission_id' => $odpSubmissionId]);

                    DB::table('odp_assets')
                        ->where('source_submission_id', $submission->id)
                        ->update(['source_submission_id' => $odpSubmissionId]);

                    DB::table('odp_ports')
                        ->where('source_submission_id', $submission->id)
                        ->update(['source_submission_id' => $odpSubmissionId]);
                }
            });
    }
};
