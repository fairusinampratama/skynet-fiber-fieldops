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

            $odcAsset = OdcAsset::query()->firstOrNew([
                'project_id' => $submission->project_id,
                'box_id' => $submission->odc_box_id,
            ]);

            $odcAsset->fill([
                'area_id' => $submission->area_id,
                'photo_path' => $submission->odc_photo_path,
                'latitude' => $submission->odc_latitude,
                'longitude' => $submission->odc_longitude,
                'source_submission_id' => $submission->id,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'status' => $odcAsset->exists && $odcAsset->olt_pon_port_id ? 'active' : 'unmapped',
            ])->save();

            $odpAsset = OdpAsset::query()->updateOrCreate(
                ['project_id' => $submission->project_id, 'box_id' => $submission->odp_box_id],
                [
                    'area_id' => $submission->area_id,
                    'odc_asset_id' => $odcAsset->id,
                    'photo_path' => $submission->odp_photo_path,
                    'latitude' => $submission->odp_latitude,
                    'longitude' => $submission->odp_longitude,
                    'core_color' => $submission->odp_core_color,
                    'source_submission_id' => $submission->id,
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                    'status' => 'active',
                ],
            );

            foreach ($submission->ports->where('asset_type', AssetType::Odc) as $port) {
                $odcAsset->ports()->updateOrCreate(
                    ['port_number' => $port->port_number],
                    ['status' => $port->status, 'source_submission_id' => $submission->id, 'updated_by' => $admin->id],
                );
            }

            foreach ($submission->ports->where('asset_type', AssetType::Odp) as $port) {
                $odpAsset->ports()->updateOrCreate(
                    ['port_number' => $port->port_number],
                    ['status' => $port->status, 'source_submission_id' => $submission->id, 'updated_by' => $admin->id],
                );
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
