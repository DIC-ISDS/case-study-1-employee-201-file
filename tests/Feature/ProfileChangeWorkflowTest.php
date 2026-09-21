<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Office;
use App\Models\Personnel;
use App\Models\Position;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Services\ProfileChangeWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileChangeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Personnel $personnel;

    private User $employee;

    private User $hrStaff;

    private User $hrApprover;

    private ProfileChangeWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->employee()->create();
        $this->hrStaff = User::factory()->hrStaff()->create();
        $this->hrApprover = User::factory()->hrApprover()->create();

        $this->personnel = Personnel::factory()->create(['user_id' => $this->employee->id]);
        Appointment::factory()->for($this->personnel)->create();

        $this->workflow = app(ProfileChangeWorkflow::class);

        // Requests are raised by the employee, which is what created_by on
        // each one records. The workflow steps below name their actor.
        $this->actingAs($this->employee);
    }

    private function draftRequest(array $items): ProfileChangeRequest
    {
        $request = ProfileChangeRequest::create([
            'personnel_id' => $this->personnel->id,
            'submitted_by' => $this->employee->id,
            'status' => RequestStatus::Draft,
            'purpose' => 'Test request',
        ]);

        foreach ($items as $field => $value) {
            $request->items()->create(['field' => $field, 'new_value' => $value]);
        }

        return $request->refresh();
    }

    /** SCENARIO 1 — the normal, successful transaction. */
    public function test_a_request_moves_from_draft_through_verification_to_approval(): void
    {
        $request = $this->draftRequest(['contact_number' => '0999-111-2222']);

        $this->assertSame(RequestStatus::Draft, $request->status);

        $this->workflow->submit($request, $this->employee);
        $this->assertSame(RequestStatus::Pending, $request->status);
        $this->assertTrue($request->isAwaitingVerification());

        $this->workflow->verify($request, $this->hrStaff, 'Documents complete.');
        $this->assertTrue($request->isVerified());
        $this->assertTrue($request->isAwaitingDecision());
        $this->assertSame($this->hrStaff->id, $request->reviewed_by);

        $this->workflow->approve($request, $this->hrApprover, 'Approved.');
        $this->assertSame(RequestStatus::Approved, $request->status);
        $this->assertSame($this->hrApprover->id, $request->decided_by);

        // The approved change is written onto the personnel record.
        $this->assertSame('0999-111-2222', $this->personnel->refresh()->contact_number);
    }

    public function test_the_old_value_is_captured_when_the_item_is_raised(): void
    {
        $original = $this->personnel->contact_number;

        $request = $this->draftRequest(['contact_number' => '0999-111-2222']);

        $this->assertSame($original, $request->items()->first()->old_value);
    }

    /** SCENARIO 2 — the alternate path: returned, corrected, resubmitted. */
    public function test_a_returned_request_can_be_corrected_and_resubmitted(): void
    {
        $request = $this->draftRequest(['address' => 'Bay, Laguna']);
        $this->workflow->submit($request, $this->employee);

        $this->workflow->returnForRevision($request, $this->hrStaff, 'Attach proof of billing.');
        $this->assertSame(RequestStatus::Returned, $request->status);
        $this->assertTrue($request->status->isEditableByEmployee());

        $this->workflow->submit($request, $this->employee);
        $this->assertSame(RequestStatus::Pending, $request->status);

        // Resubmission clears the earlier verification, so review starts over.
        $this->assertFalse($request->isVerified());
        $this->assertTrue($request->isAwaitingVerification());
    }

    public function test_a_rejected_request_is_final_and_changes_nothing(): void
    {
        $original = $this->personnel->contact_number;
        $request = $this->draftRequest(['contact_number' => '0900-000-0000']);

        $this->workflow->submit($request, $this->employee);
        $this->workflow->verify($request, $this->hrStaff);
        $this->workflow->reject($request, $this->hrApprover, 'Not supported by documents.');

        $this->assertSame(RequestStatus::Rejected, $request->status);
        $this->assertTrue($request->status->isFinal());
        $this->assertSame($original, $this->personnel->refresh()->contact_number);
    }

    /** SCENARIO 3 — an approval that touches the core business rule. */
    public function test_approving_an_office_change_transfers_the_appointment_rather_than_duplicating_it(): void
    {
        $newOffice = Office::factory()->create();
        $previous = $this->personnel->activePrimaryAppointment;

        $request = $this->draftRequest(['office_id' => (string) $newOffice->id]);
        $this->workflow->submit($request, $this->employee);
        $this->workflow->verify($request, $this->hrStaff);
        $this->workflow->approve($request, $this->hrApprover);

        $this->personnel->refresh()->load('activePrimaryAppointment');

        $this->assertSame(AppointmentStatus::Ended, $previous->refresh()->status);
        $this->assertSame($newOffice->id, $this->personnel->activePrimaryAppointment->office_id);
        // The rule still holds after the transfer.
        $this->assertSame(1, $this->personnel->appointments()->activePrimary()->count());
    }

    public function test_approving_a_position_change_keeps_the_existing_office(): void
    {
        $newPosition = Position::factory()->create();
        $originalOffice = $this->personnel->activePrimaryAppointment->office_id;

        $request = $this->draftRequest(['position_id' => (string) $newPosition->id]);
        $this->workflow->submit($request, $this->employee);
        $this->workflow->verify($request, $this->hrStaff);
        $this->workflow->approve($request, $this->hrApprover);

        $appointment = $this->personnel->refresh()->activePrimaryAppointment;

        $this->assertSame($newPosition->id, $appointment->position_id);
        $this->assertSame($originalOffice, $appointment->office_id);
    }

    public function test_every_transition_is_written_to_the_audit_trail(): void
    {
        $request = $this->draftRequest(['contact_number' => '0912-345-6789']);

        $this->workflow->submit($request, $this->employee);
        $this->workflow->verify($request, $this->hrStaff);
        $this->workflow->approve($request, $this->hrApprover);

        $actions = $request->histories()->pluck('action')->all();

        $this->assertContains('Submitted', $actions);
        $this->assertContains('Verified by HR Staff', $actions);
        $this->assertContains('Approved and applied', $actions);
        $this->assertSame(3, $request->histories()->count());
    }

    public function test_each_request_gets_a_unique_reference_number(): void
    {
        $first = $this->draftRequest(['address' => 'A']);
        $second = $this->draftRequest(['address' => 'B']);

        $this->assertNotSame($first->reference_no, $second->reference_no);
        $this->assertStringStartsWith('PCR-'.now()->year.'-', $first->reference_no);
    }
}
