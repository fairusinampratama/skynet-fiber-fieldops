<?php

namespace App\Filament\Exports;

use App\Models\Submission;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionCsvExporter
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'proyek', 'teknisi', 'area', 'tanggal_kerja', 'jenis_aset',
                'box_id', 'latitude_rencana', 'longitude_rencana', 'latitude_aktual', 'longitude_aktual', 'foto', 'warna_core', 'odc_induk',
                'port_1', 'port_2', 'port_3', 'port_4', 'port_5', 'port_6', 'port_7', 'port_8',
                'status', 'catatan_penugasan', 'catatan_review', 'ditugaskan_pada', 'diajukan_pada', 'direview_pada',
            ], ',', '"', '\\');

            Submission::query()->with(['project', 'technician', 'area', 'parentOdcAsset', 'ports'])->chunk(200, function ($submissions) use ($handle): void {
                foreach ($submissions as $submission) {
                    $ports = $submission->ports->keyBy('port_number');

                    fputcsv($handle, [
                        $submission->project?->name,
                        $submission->technician?->name,
                        $submission->area?->name,
                        $submission->work_date?->toDateString(),
                        $submission->asset_type?->getLabel(),
                        $submission->box_id,
                        $submission->planned_latitude,
                        $submission->planned_longitude,
                        $submission->latitude,
                        $submission->longitude,
                        $submission->photo_path,
                        $submission->core_color?->value,
                        $submission->parentOdcAsset?->box_id,
                        ...collect(range(1, 8))->map(fn ($port) => $ports->get($port)?->status?->getLabel())->all(),
                        $submission->status?->getLabel(),
                        $submission->assignment_notes,
                        $submission->review_notes,
                        $submission->assigned_at?->toDateTimeString(),
                        $submission->submitted_at?->toDateTimeString(),
                        $submission->reviewed_at?->toDateTimeString(),
                    ], ',', '"', '\\');
                }
            });
        }, 'submissions.csv', ['Content-Type' => 'text/csv']);
    }
}
