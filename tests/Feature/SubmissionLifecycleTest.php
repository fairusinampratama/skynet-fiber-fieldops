<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\SubmissionResource;
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

    public function test_submission_create_mutation_defaults_to_current_technician_and_draft(): void
    {
        $technician = User::factory()->technician()->create();

        $this->actingAs($technician);

        $data = SubmissionResource::mutateFormDataBeforeCreate([]);

        $this->assertSame($technician->id, $data['technician_id']);
        $this->assertSame(SubmissionStatus::Draft->value, $data['status']);
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

    public function test_technician_can_edit_only_draft_or_correction_needed_own_submissions(): void
    {
        $technician = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();

        $draft = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::Draft]);
        $correction = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::CorrectionNeeded]);
        $approved = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::Approved]);
        $otherDraft = $this->submissionWithPorts(['technician' => $otherTechnician, 'status' => SubmissionStatus::Draft]);

        $this->actingAs($technician);

        $this->assertTrue(SubmissionResource::canEdit($draft));
        $this->assertTrue(SubmissionResource::canEdit($correction));
        $this->assertFalse(SubmissionResource::canEdit($approved));
        $this->assertFalse(SubmissionResource::canEdit($otherDraft));
    }

    public function test_table_submit_action_moves_draft_to_submitted_and_correction_to_resubmitted(): void
    {
        $technician = User::factory()->technician()->create();
        $draft = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::Draft]);
        $correction = $this->submissionWithPorts(['technician' => $technician, 'status' => SubmissionStatus::CorrectionNeeded]);

        $this->actingAs($technician);

        Livewire::test(ListSubmissions::class)
            ->callTableAction('submit', $draft);

        $this->assertSame(SubmissionStatus::Submitted, $draft->refresh()->status);
        $this->assertNotNull($draft->submitted_at);

        Livewire::test(ListSubmissions::class)
            ->callTableAction('submit', $correction);

        $this->assertSame(SubmissionStatus::Resubmitted, $correction->refresh()->status);
        $this->assertNotNull($correction->submitted_at);
    }

    public function test_admin_correction_and_reject_actions_store_review_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $submitted = $this->submissionWithPorts(['status' => SubmissionStatus::Submitted]);
        $resubmitted = $this->submissionWithPorts(['status' => SubmissionStatus::Resubmitted]);

        $this->actingAs($admin);

        Livewire::test(ListSubmissions::class)
            ->callTableAction('requestCorrection', $submitted, ['review_notes' => 'Please fix coordinates.']);

        $this->assertSame(SubmissionStatus::CorrectionNeeded, $submitted->refresh()->status);
        $this->assertSame('Please fix coordinates.', $submitted->review_notes);
        $this->assertSame($admin->id, $submitted->reviewed_by);
        $this->assertNotNull($submitted->reviewed_at);

        Livewire::test(ListSubmissions::class)
            ->callTableAction('reject', $resubmitted, ['review_notes' => 'Duplicate field report.']);

        $this->assertSame(SubmissionStatus::Rejected, $resubmitted->refresh()->status);
        $this->assertSame('Duplicate field report.', $resubmitted->review_notes);
        $this->assertSame($admin->id, $resubmitted->reviewed_by);
        $this->assertNotNull($resubmitted->reviewed_at);
    }
}
