<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Enums\OdpCoreColor;
use App\Enums\PortStatus;
use App\Enums\SubmissionStatus;
use App\Models\OdcAsset;
use App\Models\OdpAsset;
use App\Models\OltPonPort;
use App\Models\User;
use App\Services\ApproveSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\CreatesFieldopsData;
use Tests\TestCase;

class ApproveSubmissionServiceTest extends TestCase
{
    use CreatesFieldopsData;
    use RefreshDatabase;

    public function test_non_admin_cannot_approve_and_no_assets_are_created(): void
    {
        $technician = User::factory()->technician()->create();
        $submission = $this->submissionWithPorts(['status' => SubmissionStatus::Submitted]);

        $this->expectException(ValidationException::class);

        try {
            app(ApproveSubmissionService::class)->approve($submission, $technician, 'Nope.');
        } finally {
            $this->assertDatabaseCount('odc_assets', 0);
            $this->assertDatabaseCount('odp_assets', 0);
            $this->assertSame(SubmissionStatus::Submitted, $submission->refresh()->status);
        }
    }

    public function test_admin_approval_creates_odc_asset_ports_and_audit_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submissionWithPorts(['asset_type' => AssetType::Odc, 'status' => SubmissionStatus::Submitted]);

        app(ApproveSubmissionService::class)->approve($submission, $admin, 'Approved for rollout.');

        $submission->refresh();
        $odcAsset = OdcAsset::query()->where('box_id', $submission->box_id)->firstOrFail();

        $this->assertSame(SubmissionStatus::Approved, $submission->status);
        $this->assertSame('Approved for rollout.', $submission->review_notes);
        $this->assertSame($admin->id, $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);

        $this->assertDatabaseCount('odp_assets', 0);
        $this->assertSame($submission->id, $odcAsset->source_submission_id);
        $this->assertSame($admin->id, $odcAsset->approved_by);
        $this->assertSame($submission->photo_path, $odcAsset->photo_path);
        $this->assertNull($odcAsset->olt_pon_port_id);
        $this->assertSame('unmapped', $odcAsset->status);
        $this->assertNotNull($odcAsset->approved_at);
        $this->assertSame(8, $odcAsset->ports()->count());
        $this->assertSame(PortStatus::Available, $odcAsset->ports()->where('port_number', 1)->firstOrFail()->status);
    }

    public function test_admin_approval_creates_odp_asset_ports_and_optional_parent_link(): void
    {
        $admin = User::factory()->admin()->create();
        $parentOdc = OdcAsset::factory()->create();
        $submission = $this->submissionWithPorts([
            'asset_type' => AssetType::Odp,
            'box_id' => 'ODP-APPROVE-1',
            'core_color' => OdpCoreColor::Hijau,
            'parent_odc_asset_id' => $parentOdc->id,
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now()->subMinute(),
        ]);

        app(ApproveSubmissionService::class)->approve($submission, $admin);

        $odpAsset = OdpAsset::query()->where('box_id', $submission->box_id)->firstOrFail();

        $this->assertDatabaseCount('odc_assets', 1);
        $this->assertSame($submission->id, $odpAsset->source_submission_id);
        $this->assertTrue($odpAsset->sourceSubmission->is($submission));
        $this->assertNotNull($odpAsset->sourceSubmission->submitted_at);
        $this->assertSame($admin->id, $odpAsset->approved_by);
        $this->assertSame($parentOdc->id, $odpAsset->odc_asset_id);
        $this->assertSame($submission->core_color, $odpAsset->core_color);
        $this->assertNotNull($odpAsset->approved_at);
        $this->assertSame(8, $odpAsset->ports()->count());
        $this->assertSame(PortStatus::Used, $odpAsset->ports()->where('port_number', 2)->firstOrFail()->status);
    }

    public function test_admin_approval_updates_existing_single_asset_and_upserts_ports(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submissionWithPorts(['asset_type' => AssetType::Odc, 'status' => SubmissionStatus::Submitted]);

        $existingOdc = OdcAsset::factory()
            ->for($submission->project)
            ->for($submission->area)
            ->create([
                'box_id' => $submission->box_id,
                'latitude' => '0.00000000',
                'longitude' => '0.00000000',
            ]);

        $existingOdc->ports()->create(['port_number' => 1, 'status' => PortStatus::Broken]);

        app(ApproveSubmissionService::class)->approve($submission, $admin);

        $this->assertDatabaseCount('odc_assets', 1);
        $this->assertDatabaseCount('odp_assets', 0);
        $this->assertSame($submission->latitude, $existingOdc->refresh()->latitude);
        $this->assertSame(8, $existingOdc->ports()->count());
        $this->assertSame(PortStatus::Available, $existingOdc->ports()->where('port_number', 1)->firstOrFail()->status);
    }

    public function test_admin_approval_preserves_existing_odc_pon_mapping(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submissionWithPorts(['asset_type' => AssetType::Odc, 'status' => SubmissionStatus::Submitted]);
        $ponPort = OltPonPort::factory()->create([
            'pon_number' => 3,
            'capacity' => 128,
        ]);

        $existingOdc = OdcAsset::factory()
            ->for($submission->project)
            ->for($submission->area)
            ->create([
                'box_id' => $submission->box_id,
                'olt_pon_port_id' => $ponPort->id,
                'status' => 'active',
            ]);

        app(ApproveSubmissionService::class)->approve($submission, $admin);

        $existingOdc->refresh();

        $this->assertSame($ponPort->id, $existingOdc->olt_pon_port_id);
        $this->assertSame('active', $existingOdc->status);
    }
}
