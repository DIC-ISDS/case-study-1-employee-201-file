<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Filament\Resources\ProfileChangeRequests\Pages\CreateProfileChangeRequest;
use App\Filament\Resources\ProfileChangeRequests\Pages\EditProfileChangeRequest;
use App\Models\Appointment;
use App\Models\Office;
use App\Models\Personnel;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercises the real Filament form, including the value input that switches
 * between a dropdown and a text box depending on the field chosen.
 */
class ProfileChangeRequestFormTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private Personnel $personnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->employee()->create();
        $this->personnel = Personnel::factory()->create(['user_id' => $this->employee->id]);
        Appointment::factory()->for($this->personnel)->create();

        $this->actingAs($this->employee);
    }

    public function test_an_employee_can_file_a_free_text_change(): void
    {
        Livewire::test(CreateProfileChangeRequest::class)
            ->fillForm([
                'personnel_id' => $this->personnel->id,
                'purpose' => 'Changed mobile carrier.',
                'items' => [
                    ['field' => 'contact_number', 'new_value_text' => '0998-777-1234'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = ProfileChangeRequest::latest('id')->first();

        $this->assertNotNull($request);
        $this->assertSame(RequestStatus::Draft, $request->status);
        $this->assertSame($this->personnel->id, $request->personnel_id);
        $this->assertSame($this->employee->id, $request->submitted_by);
        $this->assertSame('0998-777-1234', $request->items()->first()->new_value);
    }

    public function test_an_employee_can_file_a_lookup_change(): void
    {
        $office = Office::factory()->create();

        Livewire::test(CreateProfileChangeRequest::class)
            ->fillForm([
                'personnel_id' => $this->personnel->id,
                'purpose' => 'Reassignment.',
                'items' => [
                    ['field' => 'office_id', 'new_value_option' => (string) $office->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = ProfileChangeRequest::latest('id')->first()->items()->first();

        $this->assertSame('office_id', $item->field);
        $this->assertSame((string) $office->id, $item->new_value);
        $this->assertSame($office->name, $item->new_display);
    }

    public function test_several_fields_can_be_changed_in_one_request(): void
    {
        $office = Office::factory()->create();

        Livewire::test(CreateProfileChangeRequest::class)
            ->fillForm([
                'personnel_id' => $this->personnel->id,
                'items' => [
                    ['field' => 'office_id', 'new_value_option' => (string) $office->id],
                    ['field' => 'address', 'new_value_text' => 'Bay, Laguna'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $items = ProfileChangeRequest::latest('id')->first()->items()->pluck('new_value', 'field');

        $this->assertCount(2, $items);
        // Assert the values, not just the count: a count alone still passes
        // when both inputs write null.
        $this->assertSame((string) $office->id, $items['office_id']);
        $this->assertSame('Bay, Laguna', $items['address']);
    }

    public function test_a_request_with_no_changes_is_rejected(): void
    {
        Livewire::test(CreateProfileChangeRequest::class)
            ->fillForm([
                'personnel_id' => $this->personnel->id,
                'items' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['items']);
    }

    /**
     * Editing a returned request replaces its lines. Removing a line
     * soft-deletes it, so re-adding the same field has to clear the unique
     * index on (request, field) -- the exact path the demo takes.
     */
    public function test_a_returned_request_can_have_the_same_field_re_entered(): void
    {
        $request = ProfileChangeRequest::create([
            'personnel_id' => $this->personnel->id,
            'submitted_by' => $this->employee->id,
            'status' => RequestStatus::Returned,
        ]);
        $request->items()->create(['field' => 'address', 'new_value' => 'Bay, Laguna']);

        Livewire::test(EditProfileChangeRequest::class, ['record' => $request->id])
            ->fillForm([
                'items' => [
                    ['field' => 'address', 'new_value_text' => 'Calamba, Laguna'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $items = $request->fresh()->items;

        $this->assertCount(1, $items);
        $this->assertSame('Calamba, Laguna', $items->first()->new_value);
    }

    public function test_an_employee_filing_for_someone_else_is_redirected_to_their_own_record(): void
    {
        $someoneElse = Personnel::factory()->create();

        Livewire::test(CreateProfileChangeRequest::class)
            ->fillForm([
                'personnel_id' => $someoneElse->id,
                'items' => [
                    ['field' => 'address', 'new_value_text' => 'Elsewhere'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // The page forces the employee's own record regardless of the payload.
        $this->assertSame(
            $this->personnel->id,
            ProfileChangeRequest::latest('id')->first()->personnel_id,
        );
    }
}
