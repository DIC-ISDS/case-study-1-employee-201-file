<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Personnel;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    private function personnelFor(User $user): Personnel
    {
        $personnel = Personnel::factory()->create(['user_id' => $user->id]);
        Appointment::factory()->for($personnel)->create();

        return $personnel;
    }

    public function test_the_root_url_redirects_to_the_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_the_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_hr_staff_can_load_every_main_page(): void
    {
        $hrStaff = User::factory()->hrStaff()->create();
        $personnel = $this->personnelFor(User::factory()->employee()->create());

        $this->actingAs($hrStaff);

        $request = ProfileChangeRequest::create([
            'personnel_id' => $personnel->id,
            'status' => RequestStatus::Pending,
            'submitted_at' => now(),
        ]);
        $request->items()->create(['field' => 'address', 'new_value' => 'Bay, Laguna']);

        $this->get('/admin')->assertOk();
        $this->get('/admin/personnel')->assertOk();
        $this->get("/admin/personnel/{$personnel->id}")->assertOk();
        $this->get("/admin/personnel/{$personnel->id}/edit")->assertOk();
        $this->get('/admin/offices')->assertOk();
        $this->get('/admin/positions')->assertOk();
        $this->get('/admin/profile-change-requests')->assertOk();
        $this->get("/admin/profile-change-requests/{$request->id}")->assertOk();
    }

    public function test_an_employee_can_load_their_own_pages(): void
    {
        $employee = User::factory()->employee()->create();
        $personnel = $this->personnelFor($employee);

        $this->actingAs($employee);

        $this->get('/admin')->assertOk();
        $this->get('/admin/personnel')->assertOk();
        $this->get("/admin/personnel/{$personnel->id}")->assertOk();
        $this->get('/admin/profile-change-requests')->assertOk();
        $this->get('/admin/profile-change-requests/create')->assertOk();
    }

    public function test_an_employee_is_refused_the_master_data_lookups(): void
    {
        $this->actingAs(User::factory()->employee()->create());

        $this->get('/admin/offices')->assertForbidden();
        $this->get('/admin/positions')->assertForbidden();
    }

    public function test_another_employees_record_is_not_even_visible(): void
    {
        $employee = User::factory()->employee()->create();
        $this->personnelFor($employee);
        $someoneElse = $this->personnelFor(User::factory()->employee()->create());

        // The resource query is scoped to the signed-in employee, so another
        // employee's record is not found at all rather than refused -- which
        // also avoids confirming that the record exists.
        $this->actingAs($employee)
            ->get("/admin/personnel/{$someoneElse->id}")
            ->assertNotFound();
    }

    public function test_hr_can_open_the_printable_master_list(): void
    {
        $personnel = $this->personnelFor(User::factory()->employee()->create());

        $this->actingAs(User::factory()->hrStaff()->create())
            ->get(route('reports.personnel-master-list'))
            ->assertOk()
            ->assertSee('Personnel Master List')
            ->assertSee($personnel->last_name, escape: false);
    }

    public function test_the_report_is_closed_to_guests(): void
    {
        $this->get(route('reports.personnel-master-list'))->assertRedirect();
    }
}
