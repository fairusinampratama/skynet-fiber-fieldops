<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Enums\PortStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\SubmissionResource;
use App\Filament\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Filament\Resources\SubmissionResource\Pages\EditSubmission;
use App\Filament\Resources\SubmissionResource\Pages\ListSubmissions;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFieldopsData;
use Tests\TestCase;

class SubmissionLifecycleTest extends TestCase
{
    use CreatesFieldopsData;
    use RefreshDatabase;

    public function test_assignment_create_mutation_defaults_to_admin_assignment_metadata(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $data = SubmissionResource::mutateFormDataBeforeCreate([]);

        $this->assertSame($admin->id, $data['assigned_by']);
        $this->assertNotNull($data['assigned_at']);
        $this->assertSame(SubmissionStatus::Assigned->value, $data['status']);
        $this->assertTrue(SubmissionResource::canCreate());
    }

    public function test_technician_query_scope_sees_only_own_submissions_while_admin_sees_all(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();

        $ownSubmission = $this->submissionWithPorts(['technician' => $technician]);
        $otherSubmission = $this->submissionWithPorts(['technician' => $otherTechnician]);

        $this->assertTrue(Submission::query()->visibleTo($admin)->whereKey($ownSubmission)->exists());
        $this->assertTrue(Submission::query()->visibleTo($admin)->whereKey($otherSubmission)->exists());
        $this->assertTrue(Submission::query()->visibleTo($technician)->whereKey($ownSubmission)->exists());
        $this->assertFalse(Submission::query()->visibleTo($technician)->whereKey($otherSubmission)->exists());
    }

    public function test_admin_can_create_odc_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $bundle = $this->fieldopsBundle();

        $this->actingAs($admin);

        Livewire::test(CreateSubmission::class)
            ->set('data.project_id', $bundle['project']->id)
            ->set('data.technician_id', $bundle['technician']->id)
            ->set('data.area_id', $bundle['area']->id)
            ->set('data.asset_type', AssetType::Odc->value)
            ->set('data.work_date', now()->toDateString())
            ->set('data.planned_latitude', '-7.96660000')
            ->set('data.planned_longitude', '112.63260000')
            ->set('data.assignment_notes', 'ODC field work.')
            ->call('create')
            ->assertHasNoFormErrors();

        $submission = Submission::query()->firstWhere('assignment_notes', 'ODC field work.');

        $this->assertNotNull($submission);
        $this->assertSame($bundle['technician']->id, $submission->technician_id);
        $this->assertSame('-7.96660000', $submission->planned_latitude);
        $this->assertSame('112.63260000', $submission->planned_longitude);
        $this->assertNull($submission->latitude);
        $this->assertNull($submission->longitude);
        $this->assertSame(8, $submission->ports()->count());
    }

    public function test_admin_can_create_odp_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $bundle = $this->fieldopsBundle();

        $this->actingAs($admin);

        Livewire::test(CreateSubmission::class)
            ->set('data.project_id', $bundle['project']->id)
            ->set('data.technician_id', $bundle['technician']->id)
            ->set('data.area_id', $bundle['area']->id)
            ->set('data.asset_type', AssetType::Odp->value)
            ->set('data.work_date', now()->toDateString())
            ->set('data.planned_latitude', '-7.96670000')
            ->set('data.planned_longitude', '112.63270000')
            ->set('data.assignment_notes', 'ODP field work.')
            ->call('create')
            ->assertHasNoFormErrors();

        $submission = Submission::query()->firstWhere('assignment_notes', 'ODP field work.');

        $this->assertNotNull($submission);
        $this->assertSame($bundle['technician']->id, $submission->technician_id);
        $this->assertSame('-7.96670000', $submission->planned_latitude);
        $this->assertSame('112.63270000', $submission->planned_longitude);
        $this->assertNull($submission->latitude);
        $this->assertNull($submission->longitude);
        $this->assertSame(8, $submission->ports()->count());
    }

    public function test_admin_create_rejects_invalid_planned_coordinates(): void
    {
        $admin = User::factory()->admin()->create();
        $bundle = $this->fieldopsBundle();

        $this->actingAs($admin);

        Livewire::test(CreateSubmission::class)
            ->set('data.project_id', $bundle['project']->id)
            ->set('data.technician_id', $bundle['technician']->id)
            ->set('data.area_id', $bundle['area']->id)
            ->set('data.asset_type', AssetType::Odc->value)
            ->set('data.work_date', now()->toDateString())
            ->set('data.planned_latitude', '91')
            ->set('data.planned_longitude', '181')
            ->call('create')
            ->assertHasFormErrors([
                'planned_latitude' => 'max',
                'planned_longitude' => 'max',
            ]);
    }

    public function test_technician_visibility_is_based_on_assigned_technician(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();
        $ownSubmission = $this->submissionWithPorts(['technician' => $technician]);
        $otherSubmission = $this->submissionWithPorts(['technician' => $otherTechnician]);

        $this->assertTrue(Submission::query()->visibleTo($admin)->whereKey($ownSubmission)->exists());
        $this->assertTrue(Submission::query()->visibleTo($technician)->whereKey($ownSubmission)->exists());
        $this->assertFalse(Submission::query()->visibleTo($technician)->whereKey($otherSubmission)->exists());
    }

    public function test_technician_can_edit_only_assigned_or_correction_needed_own_assignments(): void
    {
        $technician = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();

        $assigned = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::Assigned]);
        $correction = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::CorrectionNeeded]);
        $approved = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::Approved]);
        $otherAssigned = $this->submissionWithPorts(['technician' => $otherTechnician, 'status' => SubmissionStatus::Assigned]);

        $this->actingAs($technician);

        $this->assertTrue(SubmissionResource::canEdit($assigned));
        $this->assertTrue(SubmissionResource::canEdit($correction));
        $this->assertFalse(SubmissionResource::canEdit($approved));
        $this->assertFalse(SubmissionResource::canEdit($otherAssigned));
    }

    public function test_submission_access_allows_technician_submission_flow_without_admin_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();

        $ownSubmission = $this->submissionWithPorts(['technician' => $technician]);
        $otherSubmission = $this->submissionWithPorts(['technician' => $otherTechnician]);
        $lockedSubmission = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::Resubmitted]);

        $this->actingAs($technician);

        $this->assertTrue(SubmissionResource::canViewAny());
        $this->assertFalse(SubmissionResource::canCreate());
        $this->assertTrue(SubmissionResource::canView($ownSubmission));
        $this->assertFalse(SubmissionResource::canView($otherSubmission));
        $this->assertFalse(SubmissionResource::canDelete($ownSubmission));
        $this->get(SubmissionResource::getUrl('view', ['record' => $lockedSubmission]))->assertOk();
        $this->get(SubmissionResource::getUrl('edit', ['record' => $lockedSubmission]))->assertForbidden();

        $this->actingAs($admin);

        $this->assertTrue(SubmissionResource::canView($ownSubmission));
        $this->assertTrue(SubmissionResource::canDelete($ownSubmission));
    }

    public function test_table_submit_action_moves_assigned_to_submitted_and_correction_to_resubmitted(): void
    {
        $technician = User::factory()->technician()->create();
        $assigned = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::Assigned]);
        $correction = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::CorrectionNeeded]);

        $this->actingAs($technician);

        Livewire::test(ListSubmissions::class)
            ->callTableAction('submit', $assigned);

        $this->assertSame(SubmissionStatus::Submitted, $assigned->refresh()->status);
        $this->assertNotNull($assigned->submitted_at);

        Livewire::test(ListSubmissions::class)
            ->callTableAction('submit', $correction);

        $this->assertSame(SubmissionStatus::Resubmitted, $correction->refresh()->status);
        $this->assertNotNull($correction->submitted_at);
    }

    public function test_technician_edit_saves_field_data_without_changing_planning_fields(): void
    {
        $technician = User::factory()->technician()->create();
        $assignment = $this->submissionWithPorts([
            'technician' => $technician,
            'status' => SubmissionStatus::Assigned,
            'assignment_notes' => 'Original admin instruction.',
            'planned_latitude' => '-7.96660000',
            'planned_longitude' => '112.63260000',
        ]);
        $otherTechnician = User::factory()->technician()->create();

        $this->actingAs($technician);

        Livewire::test(EditSubmission::class, ['record' => $assignment->getRouteKey()])
            ->set('data.project_id', null)
            ->set('data.technician_id', $otherTechnician->id)
            ->set('data.assignment_notes', 'Changed by technician.')
            ->set('data.box_id', 'ODC-FIELD-001')
            ->set('data.latitude', '-7.96661111')
            ->set('data.longitude', '112.63261111')
            ->set('data.notes', 'Field data completed.')
            ->call('save')
            ->assertHasNoFormErrors();

        $assignment->refresh();

        $this->assertSame($technician->id, $assignment->technician_id);
        $this->assertSame('Original admin instruction.', $assignment->assignment_notes);
        $this->assertSame('-7.96660000', $assignment->planned_latitude);
        $this->assertSame('112.63260000', $assignment->planned_longitude);
        $this->assertSame('ODC-FIELD-001', $assignment->box_id);
        $this->assertSame('-7.96661111', $assignment->latitude);
        $this->assertSame('112.63261111', $assignment->longitude);
        $this->assertSame('Field data completed.', $assignment->notes);
    }

    public function test_submission_port_numbers_are_not_renumbered_from_edit_form_state(): void
    {
        $technician = User::factory()->technician()->create();
        $assignment = $this->submissionWithPorts([
            'technician' => $technician,
            'status' => SubmissionStatus::Assigned,
        ]);

        $firstPort = $assignment->ports()->where('port_number', 1)->firstOrFail();

        $this->actingAs($technician);

        $component = Livewire::test(EditSubmission::class, ['record' => $assignment->getRouteKey()]);
        $firstRepeaterItemKey = array_key_first($component->get('data.ports'));

        $component
            ->set("data.ports.{$firstRepeaterItemKey}.port_number", 8)
            ->set("data.ports.{$firstRepeaterItemKey}.status", PortStatus::Used->value)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $firstPort->refresh()->port_number);
        $this->assertSame(8, $assignment->ports()->count());
        $this->assertSame(1, $assignment->ports()->where('port_number', 8)->count());
    }

    public function test_technician_can_submit_current_unsaved_edit_form_data_to_admin(): void
    {
        $technician = User::factory()->technician()->create();
        $assignment = $this->submissionWithPorts([
            'technician' => $technician,
            'status' => SubmissionStatus::Assigned,
            'box_id' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->actingAs($technician);

        Livewire::test(EditSubmission::class, ['record' => $assignment->getRouteKey()])
            ->set('data.box_id', 'ODC-FIELD-SUBMIT')
            ->set('data.latitude', '-7.96661111')
            ->set('data.longitude', '112.63261111')
            ->callAction('submitAssignment')
            ->assertHasNoFormErrors();

        $assignment->refresh();

        $this->assertSame(SubmissionStatus::Submitted, $assignment->status);
        $this->assertNotNull($assignment->submitted_at);
        $this->assertSame('ODC-FIELD-SUBMIT', $assignment->box_id);
        $this->assertSame('-7.96661111', $assignment->latitude);
        $this->assertSame('112.63261111', $assignment->longitude);
    }

    public function test_technician_edit_rejects_invalid_actual_coordinates(): void
    {
        $technician = User::factory()->technician()->create();
        $assignment = $this->submissionWithPorts([
            'technician' => $technician,
            'status' => SubmissionStatus::Assigned,
        ]);

        $this->actingAs($technician);

        Livewire::test(EditSubmission::class, ['record' => $assignment->getRouteKey()])
            ->set('data.box_id', 'ODC-FIELD-002')
            ->set('data.latitude', '-91')
            ->set('data.longitude', '-181')
            ->call('save')
            ->assertHasFormErrors([
                'latitude' => 'min',
                'longitude' => 'min',
            ]);
    }

    public function test_admin_correction_and_reject_actions_store_review_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $submitted = $this->submissionWithPorts(['status' => SubmissionStatus::Submitted]);
        $resubmitted = $this->submissionWithPorts(['status' => SubmissionStatus::Resubmitted]);

        $this->actingAs($admin);

        Livewire::test(ListSubmissions::class)
            ->mountTableAction('requestCorrection', $submitted)
            ->set('mountedActions.0.data.review_notes', 'Please fix coordinates.')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(SubmissionStatus::CorrectionNeeded, $submitted->refresh()->status);
        $this->assertSame('Please fix coordinates.', $submitted->review_notes);
        $this->assertSame($admin->id, $submitted->reviewed_by);
        $this->assertNotNull($submitted->reviewed_at);

        Livewire::test(ListSubmissions::class)
            ->mountTableAction('reject', $resubmitted)
            ->set('mountedActions.0.data.review_notes', 'Duplicate field report.')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(SubmissionStatus::Rejected, $resubmitted->refresh()->status);
        $this->assertSame('Duplicate field report.', $resubmitted->review_notes);
        $this->assertSame($admin->id, $resubmitted->reviewed_by);
        $this->assertNotNull($resubmitted->reviewed_at);
    }
}
