<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Appointment;
use App\Models\Office;
use App\Models\Personnel;
use App\Models\Position;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CORE BUSINESS RULE: an employee may hold only one active primary
 * appointment at a time.
 */
class BusinessRuleTest extends TestCase
{
    use RefreshDatabase;

    private Personnel $personnel;

    private Office $office;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        // Appointments are always encoded by HR, and every record captures
        // who created it, so the suite signs in the way the panel would.
        $this->actingAs(User::factory()->hrStaff()->create());

        $this->personnel = Personnel::factory()->create();
        $this->office = Office::factory()->create();
        $this->position = Position::factory()->create();

        Appointment::factory()->for($this->personnel)->create([
            'office_id' => $this->office->id,
            'position_id' => $this->position->id,
        ]);
    }

    public function test_a_second_active_primary_appointment_is_rejected(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        $this->personnel->appointments()->create([
            'office_id' => Office::factory()->create()->id,
            'position_id' => Position::factory()->create()->id,
            'type' => AppointmentType::Primary,
            'status' => AppointmentStatus::Active,
            'start_date' => now(),
        ]);
    }

    public function test_the_rejection_message_names_the_conflicting_appointment(): void
    {
        try {
            $this->personnel->appointments()->create([
                'office_id' => $this->office->id,
                'position_id' => $this->position->id,
                'type' => AppointmentType::Primary,
                'status' => AppointmentStatus::Active,
                'start_date' => now(),
            ]);

            $this->fail('Expected a BusinessRuleViolation.');
        } catch (BusinessRuleViolation $exception) {
            $this->assertStringContainsString($this->position->title, $exception->getMessage());
            $this->assertStringContainsString($this->office->name, $exception->getMessage());
        }

        $this->assertSame(1, $this->personnel->appointments()->count());
    }

    public function test_the_database_rejects_a_duplicate_even_when_the_model_is_bypassed(): void
    {
        $this->expectException(QueryException::class);

        DB::table('appointments')->insert([
            'personnel_id' => $this->personnel->id,
            'office_id' => $this->office->id,
            'position_id' => $this->position->id,
            'type' => AppointmentType::Primary->value,
            'status' => AppointmentStatus::Active->value,
            'start_date' => now()->toDateString(),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_secondary_appointment_is_allowed_alongside_the_primary(): void
    {
        $secondary = $this->personnel->appointments()->create([
            'office_id' => Office::factory()->create()->id,
            'position_id' => Position::factory()->create()->id,
            'type' => AppointmentType::Secondary,
            'status' => AppointmentStatus::Active,
            'start_date' => now(),
        ]);

        $this->assertTrue($secondary->exists);
        $this->assertSame(2, $this->personnel->appointments()->count());
    }

    public function test_a_new_primary_is_allowed_once_the_previous_one_has_ended(): void
    {
        $this->personnel->activePrimaryAppointment->update([
            'status' => AppointmentStatus::Ended,
            'end_date' => now()->toDateString(),
        ]);

        $replacement = $this->personnel->appointments()->create([
            'office_id' => Office::factory()->create()->id,
            'position_id' => Position::factory()->create()->id,
            'type' => AppointmentType::Primary,
            'status' => AppointmentStatus::Active,
            'start_date' => now(),
        ]);

        $this->assertTrue($replacement->isActivePrimary());
        $this->assertSame(1, $this->personnel->appointments()->activePrimary()->count());
    }

    public function test_transferring_ends_the_old_appointment_and_opens_the_new_one(): void
    {
        $previous = $this->personnel->activePrimaryAppointment;
        $newOffice = Office::factory()->create();
        $newPosition = Position::factory()->create();

        $new = app(AppointmentService::class)
            ->transferPrimary($this->personnel, $newOffice->id, $newPosition->id, 'Reassignment');

        $this->assertSame(AppointmentStatus::Ended, $previous->refresh()->status);
        $this->assertNotNull($previous->end_date);
        $this->assertSame(AppointmentStatus::Active, $new->status);
        $this->assertSame($newOffice->id, $new->office_id);
        $this->assertSame(1, $this->personnel->appointments()->activePrimary()->count());
    }

    public function test_reactivating_an_ended_appointment_is_rejected_while_another_is_active(): void
    {
        $ended = Appointment::factory()->for($this->personnel)->ended()->create([
            'office_id' => $this->office->id,
            'position_id' => $this->position->id,
        ]);

        $this->expectException(BusinessRuleViolation::class);

        $ended->update(['status' => AppointmentStatus::Active]);
    }
}
