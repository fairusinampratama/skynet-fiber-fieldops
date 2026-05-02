<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\SubmissionStatus;
use App\Models\OdcAsset;
use App\Models\OdpAsset;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveSubmissionService
{
    public function approve(Submission $submission, User $admin, ?string $reviewNotes = null): Submission
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['user' => 'Only admins can approve submissions.']);
        }

        return DB::transaction(function () use ($submission, $admin, $reviewNotes): Submission {
            $submission->load('ports');

            if ($submission->asset_type === AssetType::Odc) {
                $asset = OdcAsset::query()->firstOrNew([
                    'project_id' => $submission->project_id,
                    'box_id' => $submission->box_id,
                ]);

                $asset->fill([
                    'area_id' => $submission->area_id,
                    'photo_path' => $submission->photo_path,
                    'latitude' => $submission->latitude,
                    'longitude' => $submission->longitude,
                    'source_submission_id' => $submission->id,
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                    'status' => $asset->exists && $asset->olt_pon_port_id ? 'active' : 'unmapped',
                ])->save();

                foreach ($submission->ports as $port) {
                    $asset->ports()->updateOrCreate(
                        ['port_number' => $port->port_number],
                        ['status' => $port->status, 'source_submission_id' => $submission->id, 'updated_by' => $admin->id],
                    );
                }
            } else {
                $asset = OdpAsset::query()->updateOrCreate(
                    ['project_id' => $submission->project_id, 'box_id' => $submission->box_id],
                    [
                        'area_id' => $submission->area_id,
                        'odc_asset_id' => $submission->parent_odc_asset_id,
                        'photo_path' => $submission->photo_path,
                        'latitude' => $submission->latitude,
                        'longitude' => $submission->longitude,
                        'core_color' => $submission->core_color,
                        'source_submission_id' => $submission->id,
                        'approved_by' => $admin->id,
                        'approved_at' => now(),
                        'status' => 'active',
                    ],
                );

                foreach ($submission->ports as $port) {
                    $asset->ports()->updateOrCreate(
                        ['port_number' => $port->port_number],
                        ['status' => $port->status, 'source_submission_id' => $submission->id, 'updated_by' => $admin->id],
                    );
                }
            }

            $submission->forceFill([
                'status' => SubmissionStatus::Approved,
                'review_notes' => $reviewNotes,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return $submission;
        });
    }
}
