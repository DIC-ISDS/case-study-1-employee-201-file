<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Office;
use App\Models\Personnel;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Services\ProfileChangeWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $otherEmployee;

    private User $hrStaff;

    private User $hrApprover;

    private Personnel $personnel;

    private ProfileChangeRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->employee()->create();
        $this->otherEmployee = User::factory()->employee()->create();
        $this->hrStaff = User::factory()->hrStaff()->create();
        $this->hrApprover = User::factory()->hrApprover()->create();

        $this->personnel = Personnel::factory()->create(['user_id' => $this->employee->id]);
        Appointment::factory()->for($this->personnel)->create();

        // Signed in so the request records its owner the way the panel
        // would; the assertions below name their user explicitly.
        $this->actingAs($this->employee);

        $this->request = ProfileChangeRequest::create([
            'personnel_id' => $this->personnel->id,
            'submitted_by' => $this->employee->id,
            'status' => RequestStatus::Draft,
        ]);
        $this->request->items()->create(['field' => 'address', 'new_value' => 'Bay, Laguna']);

        app(ProfileChangeWorkflow::class)->submit($this->request, $this->employee);
    }

    public function test_only_hr_staff_may_verify(): void
    {
        $this->assertTrue($this->hrStaff->can('verify', $this->request));
        $this->assertFalse($this->hrApprover->can('verify', $this->request));
        $this->assertFalse($this->employee->can('verify', $this->request));
    }

    public function test_an_unverified_request_cannot_be_decided(): void
    {
        $this->assertTrue($this->request->isAwaitingVerification());
        $this->assertFalse($this->hrApprover->can('decide', $this->request));
    }

    public function test_only_the_hr_approver_may_decide_a_verified_request(): void
    {
        app(ProfileChangeWorkflow::class)->verify($this->request, $this->hrStaff);

        $this->assertTrue($this->hrApprover->can('decide', $this->request));
        $this->assertFalse($this->hrStaff->can('decide', $this->request));
        $this->assertFalse($this->employee->can('decide', $this->request));
    }

    public function test_an_employee_cannot_see_another_employees_request(): void
    {
        $this->assertTrue($this->employee->can('view', $this->request));
        $this->assertFalse($this->otherEmployee->can('view', $this->request));
        $this->assertTrue($this->hrStaff->can('view', $this->request));
    }

    public function test_an_employee_cannot_edit_a_request_once_it_is_pending(): void
    {
        $this->assertSame(RequestStatus::Pending, $this->request->status);
        $this->assertFalse($this->employee->can('update', $this->request));
    }

    public function test_an_employee_may_edit_a_returned_request(): void
    {
        app(ProfileChangeWorkflow::class)->returnForRevision($this->request, $this->hrStaff, 'Fix this.');

        $this->assertTrue($this->employee->can('update', $this->request));
    }

    public function test_only_hr_may_maintain_the_personnel_master(): void
    {
        $this->assertTrue($this->hrStaff->can('update', $this->personnel));
        $this->assertTrue($this->hrApprover->can('update', $this->personnel));
        // Employees change their profile through a request, never directly.
        $this->assertFalse($this->employee->can('update', $this->personnel));
    }

    public function test_an_employee_may_view_only_their_own_personnel_record(): void
    {
        $this->assertTrue($this->employee->can('view', $this->personnel));
        $this->assertFalse($this->otherEmployee->can('view', $this->personnel));
    }

    public function test_only_hr_may_reach_the_master_data_lookups(): void
    {
        $office = Office::factory()->create();

        $this->assertTrue($this->hrStaff->can('viewAny', Office::class));
        $this->assertFalse($this->employee->can('viewAny', Office::class));
        $this->assertFalse($this->employee->can('update', $office));
    }
}
