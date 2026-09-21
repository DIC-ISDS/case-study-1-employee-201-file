<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->isHr() || $appointment->personnel?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isHr();
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->isHr();
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->isHrApprover();
    }

    /**
     * Restoring reopens the appointment, so the one-active-primary rule is checked again on save.
     */
    public function restore(User $user, Appointment $appointment): bool
    {
        return $user->isHrApprover();
    }

    /**
     * Permanent deletion is deliberately not offered: a soft-deleted record is
     * still part of the audit history, and nothing in the MVP needs it gone.
     */
    public function forceDelete(User $user, Appointment $appointment): bool
    {
        return false;
    }
}
