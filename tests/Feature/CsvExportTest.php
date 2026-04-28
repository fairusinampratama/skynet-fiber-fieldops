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
            'review_notes' => 'Looks good.',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/exports/submissions.csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('submissions.csv', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('project,technician,team,area,work_date,odc_id', $csv);
        $this->assertStringContainsString('odc_port_1,odc_port_2,odc_port_3,odc_port_4,odc_port_5,odc_port_6,odc_port_7,odc_port_8', $csv);
        $this->assertStringContainsString('odp_port_1,odp_port_2,odp_port_3,odp_port_4,odp_port_5,odp_port_6,odp_port_7,odp_port_8', $csv);
        $this->assertStringContainsString($submission->project->name, $csv);
        $this->assertStringContainsString($submission->technician->name, $csv);
        $this->assertStringContainsString($submission->odc_box_id, $csv);
        $this->assertStringContainsString($submission->odp_box_id, $csv);
        $this->assertStringContainsString('approved', $csv);
        $this->assertStringContainsString('Looks good.', $csv);
    }
}
