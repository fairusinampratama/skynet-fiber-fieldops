<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFieldopsData;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use CreatesFieldopsData;
    use RefreshDatabase;

    public function test_csv_export_requires_authenticated_admin(): void
    {
        $technician = User::factory()->technician()->create();

        $this->get('/exports/submissions.csv')->assertRedirect('/admin/login');
        $this->actingAs($technician)->get('/exports/submissions.csv')->assertForbidden();
    }

    public function test_admin_csv_export_contains_expected_headers_and_submission_data(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submissionWithPorts([
            'status' => SubmissionStatus::Approved,
            'review_notes' => 'Sudah sesuai.',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/exports/submissions.csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('submissions.csv', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('proyek,teknisi,area,tanggal_kerja,jenis_aset,box_id,latitude_rencana,longitude_rencana,latitude_aktual,longitude_aktual', $csv);
        $this->assertStringContainsString('port_1,port_2,port_3,port_4,port_5,port_6,port_7,port_8', $csv);
        $this->assertStringContainsString($submission->project->name, $csv);
        $this->assertStringContainsString($submission->technician->name, $csv);
        $this->assertStringContainsString($submission->box_id, $csv);
        $this->assertStringContainsString('Disetujui', $csv);
        $this->assertStringContainsString('Sudah sesuai.', $csv);
    }

}
