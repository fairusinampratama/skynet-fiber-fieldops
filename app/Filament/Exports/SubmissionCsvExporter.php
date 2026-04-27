<?php

namespace App\Filament\Exports;

use App\Enums\AssetType;
use App\Models\Submission;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionCsvExporter
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'project', 'technician', 'team', 'area', 'work_date',
                'odc_id', 'odc_latitude', 'odc_longitude', 'odc_photo',
                'odp_id', 'odp_latitude', 'odp_longitude', 'odp_photo', 'odp_core_color',
                'odc_port_1', 'odc_port_2', 'odc_port_3', 'odc_port_4', 'odc_port_5', 'odc_port_6', 'odc_port_7', 'odc_port_8',
                'odp_port_1', 'odp_port_2', 'odp_port_3', 'odp_port_4', 'odp_port_5', 'odp_port_6', 'odp_port_7', 'odp_port_8',
                'status', 'review_notes', 'submitted_at', 'reviewed_at',
            ]);

            Submission::query()->with(['project', 'technician', 'team', 'area', 'ports'])->chunk(200, function ($submissions) use ($handle): void {
                foreach ($submissions as $submission) {
                    $odcPorts = $submission->ports->where('asset_type', AssetType::Odc)->keyBy('port_number');
                    $odpPorts = $submission->ports->where('asset_type', AssetType::Odp)->keyBy('port_number');

                    fputcsv($handle, [
                        $submission->project?->name,
                        $submission->technician?->name,
                        $submission->team?->name,
                        $submission->area?->name,
                        $submission->work_date?->toDateString(),
                        $submission->odc_box_id,
                        $submission->odc_latitude,
                        $submission->odc_longitude,
                        $submission->odc_photo_path,
                        $submission->odp_box_id,
                        $submission->odp_latitude,
                        $submission->odp_longitude,
                        $submission->odp_photo_path,
                        $submission->odp_core_color?->value,
                        ...collect(range(1, 8))->map(fn ($port) => $odcPorts->get($port)?->status?->value)->all(),
                        ...collect(range(1, 8))->map(fn ($port) => $odpPorts->get($port)?->status?->value)->all(),
                        $submission->status?->value,
                        $submission->review_notes,
                        $submission->submitted_at?->toDateTimeString(),
                        $submission->reviewed_at?->toDateTimeString(),
                    ]);
                }
            });
        }, 'submissions.csv', ['Content-Type' => 'text/csv']);
    }
}
