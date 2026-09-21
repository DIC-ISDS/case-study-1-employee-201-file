<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\EmploymentStatus;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Office;
use App\Models\Personnel;
use App\Models\Position;
use App\Models\ProfileChangeHistory;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Logins, one per role -------------------------------------------
        // Created first so that everything seeded after them has a real owner:
        // created_by is filled from the authenticated user, and the seeder
        // signs in as the role that would realistically have encoded each
        // record rather than writing the column by hand.
        $employeeUser = User::create([
            'name' => 'Maria Santos',
            'email' => 'employee@example.com',
            'password' => 'password',
            'role' => UserRole::Employee,
        ]);

        $hrStaffUser = User::create([
            'name' => 'Jose Cruz',
            'email' => 'hrstaff@example.com',
            'password' => 'password',
            'role' => UserRole::HrStaff,
        ]);

        $hrApproverUser = User::create([
            'name' => 'Ana Reyes',
            'email' => 'hrapprover@example.com',
            'password' => 'password',
            'role' => UserRole::HrApprover,
        ]);

        // HR Staff maintain the master data.
        Auth::login($hrStaffUser);

        $offices = collect([
            ['code' => 'HRDO', 'name' => 'Human Resource Development Office'],
            ['code' => 'ICS', 'name' => 'Institute of Computer Science'],
            ['code' => 'OUR', 'name' => 'Office of the University Registrar'],
            ['code' => 'ACCT', 'name' => 'Accounting Office'],
            ['code' => 'LIB', 'name' => 'University Library'],
            ['code' => 'OVCA', 'name' => 'Office of the Vice Chancellor for Administration'],
        ])->mapWithKeys(fn ($office) => [$office['code'] => Office::create($office)]);

        $positions = collect([
            ['title' => 'Administrative Assistant II', 'salary_grade' => 8],
            ['title' => 'Administrative Officer III', 'salary_grade' => 14],
            ['title' => 'Instructor I', 'salary_grade' => 12],
            ['title' => 'Assistant Professor I', 'salary_grade' => 18],
            ['title' => 'Associate Professor II', 'salary_grade' => 22],
            ['title' => 'Computer Programmer II', 'salary_grade' => 15],
            ['title' => 'Librarian II', 'salary_grade' => 16],
            ['title' => 'HR Management Officer II', 'salary_grade' => 15],
        ])->mapWithKeys(fn ($position) => [$position['title'] => Position::create($position)]);

        // --- Personnel master ------------------------------------------------
        $roster = [
            ['Santos', 'Maria', 'Lopez', 'ICS', 'Instructor I', EmploymentStatus::Permanent, $employeeUser],
            ['Cruz', 'Jose', 'Bautista', 'HRDO', 'HR Management Officer II', EmploymentStatus::Permanent, $hrStaffUser],
            ['Reyes', 'Ana', 'Villanueva', 'HRDO', 'Administrative Officer III', EmploymentStatus::Permanent, $hrApproverUser],
            ['Dela Cruz', 'Juan', 'Ramos', 'ICS', 'Assistant Professor I', EmploymentStatus::Permanent, null],
            ['Garcia', 'Liza', 'Mendoza', 'ICS', 'Computer Programmer II', EmploymentStatus::Contractual, null],
            ['Torres', 'Miguel', 'Aquino', 'OUR', 'Administrative Assistant II', EmploymentStatus::Permanent, null],
            ['Ramos', 'Cristina', 'Diaz', 'OUR', 'Administrative Officer III', EmploymentStatus::Permanent, null],
            ['Flores', 'Antonio', 'Castro', 'ACCT', 'Administrative Assistant II', EmploymentStatus::Casual, null],
            ['Mendoza', 'Rosa', 'Lim', 'ACCT', 'Administrative Officer III', EmploymentStatus::Permanent, null],
            ['Bautista', 'Rafael', 'Ocampo', 'LIB', 'Librarian II', EmploymentStatus::Permanent, null],
            ['Villanueva', 'Grace', 'Perez', 'LIB', 'Administrative Assistant II', EmploymentStatus::Temporary, null],
            ['Aquino', 'Paolo', 'Rivera', 'OVCA', 'Administrative Officer III', EmploymentStatus::Permanent, null],
            ['Castro', 'Elena', 'Navarro', 'OVCA', 'Administrative Assistant II', EmploymentStatus::Permanent, null],
            ['Lim', 'Benjamin', 'Soriano', 'ICS', 'Associate Professor II', EmploymentStatus::Permanent, null],
            ['Ocampo', 'Teresa', 'Gomez', 'ICS', 'Instructor I', EmploymentStatus::Contractual, null],
            ['Rivera', 'Daniel', 'Salazar', 'HRDO', 'Administrative Assistant II', EmploymentStatus::Permanent, null],
            ['Navarro', 'Sofia', 'Del Rosario', 'OUR', 'Administrative Assistant II', EmploymentStatus::Permanent, null],
            ['Gomez', 'Andres', 'Fernandez', 'ACCT', 'Administrative Officer III', EmploymentStatus::Permanent, null],
            ['Salazar', 'Patricia', 'Morales', 'LIB', 'Librarian II', EmploymentStatus::Permanent, null],
            ['Fernandez', 'Ricardo', 'Cordero', 'OVCA', 'Computer Programmer II', EmploymentStatus::Contractual, null],
            ['Morales', 'Isabel', 'Guevarra', 'ICS', 'Assistant Professor I', EmploymentStatus::Permanent, null],
            ['Cordero', 'Victor', 'Alonzo', 'OUR', 'Administrative Officer III', EmploymentStatus::Permanent, null],
            ['Guevarra', 'Carmen', 'Padilla', 'ACCT', 'Administrative Assistant II', EmploymentStatus::Resigned, null],
            ['Padilla', 'Emilio', 'Herrera', 'LIB', 'Administrative Assistant II', EmploymentStatus::Resigned, null],
        ];

        foreach ($roster as $index => [$last, $first, $middle, $officeCode, $positionTitle, $status, $user]) {
            $personnel = Personnel::create([
                'employee_no' => sprintf('2024-%04d', 1001 + $index),
                'last_name' => $last,
                'first_name' => $first,
                'middle_name' => $middle,
                'email' => strtolower(str_replace([' ', "'"], '', $first.'.'.$last)).'@uplb.edu.ph',
                'contact_number' => sprintf('0917-%03d-%04d', 100 + $index, 1000 + $index),
                'address' => 'Los Baños, Laguna',
                'birth_date' => now()->subYears(25 + ($index % 30))->subDays($index * 11),
                'employment_status' => $status,
                'user_id' => $user?->id,
            ]);

            // Resigned staff keep their appointment history but hold no active
            // primary appointment.
            $personnel->appointments()->create([
                'office_id' => $offices[$officeCode]->id,
                'position_id' => $positions[$positionTitle]->id,
                'type' => AppointmentType::Primary,
                'status' => $status->isActive() ? AppointmentStatus::Active : AppointmentStatus::Ended,
                'start_date' => now()->subYears(1)->subDays($index * 17)->toDateString(),
                'end_date' => $status->isActive() ? null : now()->subMonths(2)->toDateString(),
            ]);
        }

        $this->seedRequests($offices, $positions, $employeeUser, $hrStaffUser, $hrApproverUser);

        Auth::logout();
    }

    private function seedRequests($offices, $positions, User $employeeUser, User $hrStaff, User $hrApprover): void
    {
        $maria = Personnel::where('user_id', $employeeUser->id)->first();
        $juan = Personnel::where('last_name', 'Dela Cruz')->first();
        $liza = Personnel::where('last_name', 'Garcia')->first();
        $miguel = Personnel::where('last_name', 'Torres')->first();
        $rosa = Personnel::where('last_name', 'Mendoza')->first();

        // Draft — the employee is still working on it, and owns it.
        Auth::login($employeeUser);

        $draft = ProfileChangeRequest::create([
            'personnel_id' => $maria->id,
            'submitted_by' => $employeeUser->id,
            'status' => RequestStatus::Draft,
            'purpose' => 'Updating my contact number after changing mobile carrier.',
        ]);
        $draft->items()->create([
            'field' => 'contact_number',
            'old_value' => $maria->contact_number,
            'new_value' => '0998-777-1234',
        ]);
        $this->history($draft, $employeeUser, 'Created', null, RequestStatus::Draft);

        // The rest were encoded by HR Staff on the employee's behalf, which
        // is what created_by will show on each of them.
        Auth::login($hrStaff);

        // Pending, not yet verified — waiting on HR Staff.
        $pending = ProfileChangeRequest::create([
            'personnel_id' => $juan->id,
            'status' => RequestStatus::Pending,
            'purpose' => 'Reassignment to the Office of the University Registrar effective this term.',
            'submitted_at' => now()->subDays(2),
        ]);
        $pending->items()->create([
            'field' => 'office_id',
            'old_value' => (string) $juan->activePrimaryAppointment->office_id,
            'new_value' => (string) $offices['OUR']->id,
        ]);
        $this->history($pending, null, 'Created', null, RequestStatus::Draft, now()->subDays(3));
        $this->history($pending, null, 'Submitted', RequestStatus::Draft, RequestStatus::Pending, now()->subDays(2));

        // Pending and verified — sitting with the HR Approver.
        $verified = ProfileChangeRequest::create([
            'personnel_id' => $liza->id,
            'status' => RequestStatus::Pending,
            'purpose' => 'Promotion to Assistant Professor I approved by the department.',
            'submitted_at' => now()->subDays(4),
            'reviewed_by' => $hrStaff->id,
            'reviewed_at' => now()->subDay(),
            'review_remarks' => 'Supporting documents complete and verified against the 201 file.',
        ]);
        $verified->items()->create([
            'field' => 'position_id',
            'old_value' => (string) $liza->activePrimaryAppointment->position_id,
            'new_value' => (string) $positions['Assistant Professor I']->id,
        ]);
        $this->history($verified, null, 'Submitted', RequestStatus::Draft, RequestStatus::Pending, now()->subDays(4));
        $this->history($verified, $hrStaff, 'Verified by HR Staff', RequestStatus::Pending, RequestStatus::Pending, now()->subDay());
        $this->lastTouchedBy($verified, $hrStaff);

        // Returned — sent back to the employee for more information.
        $returned = ProfileChangeRequest::create([
            'personnel_id' => $miguel->id,
            'status' => RequestStatus::Returned,
            'purpose' => 'Change of registered home address.',
            'submitted_at' => now()->subDays(6),
            'decision_remarks' => 'Please attach proof of billing for the new address.',
        ]);
        $returned->items()->create([
            'field' => 'address',
            'old_value' => $miguel->address,
            'new_value' => 'Bay, Laguna',
        ]);
        $this->history($returned, null, 'Submitted', RequestStatus::Draft, RequestStatus::Pending, now()->subDays(6));
        $this->history($returned, $hrStaff, 'Returned for revision', RequestStatus::Pending, RequestStatus::Returned, now()->subDays(5), 'Please attach proof of billing for the new address.');
        $this->lastTouchedBy($returned, $hrStaff);

        // Approved and already applied.
        $approved = ProfileChangeRequest::create([
            'personnel_id' => $rosa->id,
            'status' => RequestStatus::Approved,
            'purpose' => 'Surname correction per PSA record.',
            'submitted_at' => now()->subDays(20),
            'reviewed_by' => $hrStaff->id,
            'reviewed_at' => now()->subDays(18),
            'decided_by' => $hrApprover->id,
            'decided_at' => now()->subDays(17),
            'decision_remarks' => 'Approved. PSA certificate on file.',
        ]);
        $approved->items()->create([
            'field' => 'contact_number',
            'old_value' => '0917-000-0000',
            'new_value' => $rosa->contact_number,
        ]);
        $this->history($approved, null, 'Submitted', RequestStatus::Draft, RequestStatus::Pending, now()->subDays(20));
        $this->history($approved, $hrStaff, 'Verified by HR Staff', RequestStatus::Pending, RequestStatus::Pending, now()->subDays(18));
        $this->history($approved, $hrApprover, 'Approved and applied', RequestStatus::Pending, RequestStatus::Approved, now()->subDays(17), 'Approved. PSA certificate on file.');
        $this->lastTouchedBy($approved, $hrApprover);

        // Rejected.
        $rejected = ProfileChangeRequest::create([
            'personnel_id' => $miguel->id,
            'status' => RequestStatus::Rejected,
            'purpose' => 'Request to change employment status to Permanent.',
            'submitted_at' => now()->subDays(30),
            'reviewed_by' => $hrStaff->id,
            'reviewed_at' => now()->subDays(29),
            'decided_by' => $hrApprover->id,
            'decided_at' => now()->subDays(28),
            'decision_remarks' => 'Plantilla item not yet available. File again next cycle.',
        ]);
        $rejected->items()->create([
            'field' => 'employment_status',
            'old_value' => EmploymentStatus::Casual->value,
            'new_value' => EmploymentStatus::Permanent->value,
        ]);
        $this->history($rejected, null, 'Submitted', RequestStatus::Draft, RequestStatus::Pending, now()->subDays(30));
        $this->history($rejected, $hrApprover, 'Rejected', RequestStatus::Pending, RequestStatus::Rejected, now()->subDays(28), 'Plantilla item not yet available. File again next cycle.');
        $this->lastTouchedBy($rejected, $hrApprover);
    }

    /**
     * Seeded requests are written in one shot, so updated_by is set afterwards
     * to name whoever last acted on them -- as the workflow would have.
     */
    private function lastTouchedBy(ProfileChangeRequest $request, User $actor): void
    {
        $request->forceFill(['updated_by' => $actor->id])->saveQuietly();
    }

    private function history(
        ProfileChangeRequest $request,
        ?User $actor,
        string $action,
        ?RequestStatus $from,
        ?RequestStatus $to,
        $at = null,
        ?string $remarks = null,
    ): void {
        ProfileChangeHistory::create([
            'profile_change_request_id' => $request->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'remarks' => $remarks,
            'created_at' => $at ?? now(),
        ]);
    }
}
