<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Personnel;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    /**
     * Move an employee to a new office/position.
     *
     * Ending the current active primary appointment before creating the new
     * one is what keeps the one-active-primary rule satisfied: the two writes
     * share a transaction, so a failure part-way leaves the employee with
     * exactly the appointment they started with.
     */
    public function transferPrimary(
        Personnel $personnel,
        int $officeId,
        int $positionId,
        ?string $remarks = null,
    ): Appointment {
        return DB::transaction(function () use ($personnel, $officeId, $positionId, $remarks) {
            $current = $personnel->activePrimaryAppointment()->lockForUpdate()->first();

            $current?->update([
                'status' => AppointmentStatus::Ended,
                'end_date' => now()->toDateString(),
                'remarks' => $remarks ?? $current->remarks,
            ]);

            return $personnel->appointments()->create([
                'office_id' => $officeId,
                'position_id' => $positionId,
                'type' => AppointmentType::Primary,
                'status' => AppointmentStatus::Active,
                'start_date' => now()->toDateString(),
                'remarks' => $remarks,
            ]);
        });
    }

    public function endPrimary(Personnel $personnel, ?string $remarks = null): ?Appointment
    {
        $current = $personnel->activePrimaryAppointment()->first();

        $current?->update([
            'status' => AppointmentStatus::Ended,
            'end_date' => now()->toDateString(),
            'remarks' => $remarks ?? $current->remarks,
        ]);

        return $current;
    }
}
