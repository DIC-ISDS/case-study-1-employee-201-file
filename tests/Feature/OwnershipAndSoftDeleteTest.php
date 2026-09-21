<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Filament\Resources\Offices\Pages\ListOffices;
use App\Filament\Resources\Personnel\Pages\EditPersonnel;
use App\Filament\Resources\ProfileChangeRequests\ProfileChangeRequestResource;
use App\Models\Appointment;
use App\Models\Office;
use App\Models\Personnel;
use App\Models\Position;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Record ownership and soft deletes: every primary record names the user who
 * created it, normal deletion hides rather than destroys, and neither one is
 * something a form payload can influence.
 */
class OwnershipAndSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $hrStaff;

    private User $hrApprover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hrStaff = User::factory()->hrStaff()->create();
        $this->hrApprover = User::factory()->hrApprover()->create();
    }

    public function test_created_by_is_taken_from_the_signed_in_user(): void
    {
        $this->actingAs($this->hrStaff);

        $office = Office::create(['code' => 'TEST', 'name' => 'Test Office']);

        $this->assertSame($this->hrStaff->id, $office->created_by);
        $this->assertSame($this->hrStaff->id, $office->creator->id);
    }

    /**
     * The column is not fillable, so a tampered form payload cannot claim a
     * record for someone else.
     */
    public function test_created_by_cannot_be_set_from_the_request_payload(): void
    {
        $this->actingAs($this->hrStaff);

        $office = Office::create([
            'code' => 'SPOOF',
            'name' => 'Spoofed Office',
            'created_by' => $this->hrApprover->id,
        ]);

        $this->assertSame($this->hrStaff->id, $office->fresh()->created_by);
    }

    public function test_updated_by_stays_empty_until_the_record_is_changed(): void
    {
        $this->actingAs($this->hrStaff);
        $position = Position::create(['title' => 'Test Position', 'salary_grade' => 10]);

        $this->assertNull($position->updated_by);

        $this->actingAs($this->hrApprover);
        $position->update(['salary_grade' => 11]);

        $this->assertSame($this->hrApprover->id, $position->fresh()->updated_by);
        // The original owner does not change hands.
        $this->assertSame($this->hrStaff->id, $position->fresh()->created_by);
    }

    public function test_deleting_hides_the_record_without_destroying_it(): void
    {
        $this->actingAs($this->hrApprover);
        $office = Office::factory()->create();

        $office->delete();

        $this->assertSame(0, Office::whereKey($office->id)->count());
        $this->assertSame(1, Office::withTrashed()->whereKey($office->id)->count());
        $this->assertDatabaseHas('offices', ['id' => $office->id]);
        $this->assertNotNull($office->fresh()->deleted_at);
    }

    public function test_a_deleted_record_can_be_restored(): void
    {
        $this->actingAs($this->hrApprover);
        $personnel = Personnel::factory()->create();

        $personnel->delete();
        $personnel->restore();

        $this->assertNull($personnel->fresh()->deleted_at);
        $this->assertSame(1, Personnel::whereKey($personnel->id)->count());
    }

    public function test_only_the_hr_approver_may_restore(): void
    {
        $personnel = Personnel::factory()->create();
        $employee = User::factory()->employee()->create();

        $this->assertTrue($this->hrApprover->can('restore', $personnel));
        $this->assertFalse($this->hrStaff->can('restore', $personnel));
        $this->assertFalse($employee->can('restore', $personnel));
    }

    /** Permanent deletion is deliberately closed to every role. */
    public function test_nobody_may_permanently_delete_a_record(): void
    {
        $personnel = Personnel::factory()->create();

        $this->assertFalse($this->hrApprover->can('forceDelete', $personnel));
        $this->assertFalse($this->hrStaff->can('forceDelete', $personnel));
    }

    /**
     * The one-active-primary index is built on a column that goes null once a
     * row is trashed, so a deleted appointment no longer holds the slot.
     */
    public function test_a_deleted_appointment_frees_the_employee_for_a_new_primary(): void
    {
        $this->actingAs($this->hrStaff);

        $personnel = Personnel::factory()->create();
        $appointment = Appointment::factory()->for($personnel)->create();

        $appointment->delete();

        $replacement = $personnel->appointments()->create([
            'office_id' => Office::factory()->create()->id,
            'position_id' => Position::factory()->create()->id,
            'type' => AppointmentType::Primary,
            'status' => AppointmentStatus::Active,
            'start_date' => now(),
        ]);

        $this->assertTrue($replacement->isActivePrimary());
        $this->assertSame(1, $personnel->appointments()->activePrimary()->count());
    }

    /** ...and restoring it is refused while the replacement is still active. */
    public function test_restoring_an_appointment_still_honours_the_business_rule(): void
    {
        $this->actingAs($this->hrStaff);

        $personnel = Personnel::factory()->create();
        $appointment = Appointment::factory()->for($personnel)->create();
        $appointment->delete();

        Appointment::factory()->for($personnel)->create();

        $this->expectException(BusinessRuleViolation::class);

        $appointment->restore();
    }

    /**
     * The same unique-index problem applies to request lines: removing a line
     * and adding the same field again must not trip the constraint.
     */
    public function test_a_field_can_be_requested_again_after_its_line_is_removed(): void
    {
        $this->actingAs($this->hrStaff);

        $personnel = Personnel::factory()->create();
        Appointment::factory()->for($personnel)->create();

        $request = ProfileChangeRequest::create([
            'personnel_id' => $personnel->id,
            'status' => RequestStatus::Draft,
        ]);

        $request->items()->create(['field' => 'address', 'new_value' => 'Bay, Laguna'])->delete();
        $replacement = $request->items()->create(['field' => 'address', 'new_value' => 'Calamba, Laguna']);

        $this->assertSame('Calamba, Laguna', $replacement->new_value);
        $this->assertSame(1, $request->items()->count());
        $this->assertSame(2, $request->items()->withTrashed()->count());
    }

    public function test_a_deleted_request_is_gone_from_the_listing_but_still_on_file(): void
    {
        $employee = User::factory()->employee()->create();
        $personnel = Personnel::factory()->create(['user_id' => $employee->id]);
        Appointment::factory()->for($personnel)->create();

        $this->actingAs($employee);

        $request = ProfileChangeRequest::create([
            'personnel_id' => $personnel->id,
            'status' => RequestStatus::Draft,
        ]);
        $request->items()->create(['field' => 'address', 'new_value' => 'Bay, Laguna']);

        $this->assertSame($employee->id, $request->created_by);

        $request->delete();

        $this->actingAs($this->hrStaff);
        $this->get('/admin/profile-change-requests')
            ->assertOk()
            ->assertDontSee($request->reference_no);

        $this->assertDatabaseHas('profile_change_requests', ['id' => $request->id]);
    }

    public function test_a_deleted_request_no_longer_counts_as_waiting_on_anyone(): void
    {
        $personnel = Personnel::factory()->create();
        Appointment::factory()->for($personnel)->create();

        $this->actingAs($this->hrStaff);

        $request = ProfileChangeRequest::create([
            'personnel_id' => $personnel->id,
            'status' => RequestStatus::Pending,
            'submitted_at' => now(),
        ]);

        $badge = fn () => ProfileChangeRequestResource::getNavigationBadge();

        $this->assertSame('1', $badge());

        $request->delete();

        $this->assertNull($badge());
    }

    /**
     * The demo hinges on this working in the panel, not just on the model:
     * delete an office, find it again through the Trashed filter, restore it.
     */
    public function test_the_panel_can_delete_find_and_restore_a_record(): void
    {
        $this->actingAs($this->hrApprover);

        $office = Office::factory()->create(['name' => 'Accounting Office']);

        Livewire::test(ListOffices::class)
            ->assertCanSeeTableRecords([$office])
            ->callAction(TestAction::make('delete')->table($office));

        $this->assertSoftDeleted($office);

        Livewire::test(ListOffices::class)
            ->assertCanNotSeeTableRecords([$office])
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$office])
            ->callAction(TestAction::make('restore')->table($office));

        $this->assertNotSoftDeleted($office);
    }

    /** HR Staff may not restore, so the panel must not offer the action. */
    public function test_the_panel_hides_restore_from_hr_staff(): void
    {
        $this->actingAs($this->hrApprover);
        $office = Office::factory()->create();
        $office->delete();

        $this->actingAs($this->hrStaff);

        Livewire::test(ListOffices::class)
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$office])
            ->assertActionHidden(TestAction::make('restore')->table($office));
    }

    /** The 201 file shows who encoded it, and offers no way to change that. */
    public function test_the_personnel_record_shows_its_trail(): void
    {
        $this->actingAs($this->hrStaff);

        $personnel = Personnel::factory()->create();
        Appointment::factory()->for($personnel)->create();

        $this->get("/admin/personnel/{$personnel->id}")
            ->assertOk()
            ->assertSee('Record trail')
            ->assertSee('Encoded by')
            ->assertSee($this->hrStaff->name);

        // ...and the edit form offers no ownership input at all, so there is
        // nothing for a user to pick or a payload to overwrite.
        Livewire::test(EditPersonnel::class, ['record' => $personnel->id])
            ->assertFormFieldDoesNotExist('created_by')
            ->assertFormFieldDoesNotExist('updated_by');
    }

    public function test_seeded_records_all_carry_an_owner(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([Office::class, Position::class, Personnel::class, Appointment::class, ProfileChangeRequest::class] as $model) {
            $this->assertSame(
                0,
                $model::whereNull('created_by')->count(),
                $model.' has records without an owner.',
            );
        }
    }
}
