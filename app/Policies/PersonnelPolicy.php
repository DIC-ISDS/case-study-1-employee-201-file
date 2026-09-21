<?php

namespace App\Policies;

use App\Models\Personnel;
use App\Models\User;

class PersonnelPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Employees see only their own record; see the resource query scope.
    }

    public function view(User $user, Personnel $personnel): bool
    {
        return $user->isHr() || $personnel->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isHr();
    }

    /**
     * Employees change their profile through a Profile Change Request, never
     * by editing the master record directly. That is the point of the module.
     */
    public function update(User $user, Personnel $personnel): bool
    {
        return $user->isHr();
    }

    public function delete(User $user, Personnel $personnel): bool
    {
        return $user->isHrApprover();
    }

    /**
     * A 201 file removed in error is restored by the HR Approver.
     */
    public function restore(User $user, Personnel $personnel): bool
    {
        return $user->isHrApprover();
    }

    /**
     * Permanent deletion is deliberately not offered: a soft-deleted record is
     * still part of the audit history, and nothing in the MVP needs it gone.
     */
    public function forceDelete(User $user, Personnel $personnel): bool
    {
        return false;
    }
}
