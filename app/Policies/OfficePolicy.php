<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;

class OfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isHr();
    }

    public function view(User $user, Office $office): bool
    {
        return $user->isHr();
    }

    public function create(User $user): bool
    {
        return $user->isHr();
    }

    public function update(User $user, Office $office): bool
    {
        return $user->isHr();
    }

    public function delete(User $user, Office $office): bool
    {
        return $user->isHrApprover();
    }

    /**
     * Whoever may delete master data may put it back.
     */
    public function restore(User $user, Office $office): bool
    {
        return $user->isHrApprover();
    }

    /**
     * Permanent deletion is deliberately not offered: a soft-deleted record is
     * still part of the audit history, and nothing in the MVP needs it gone.
     */
    public function forceDelete(User $user, Office $office): bool
    {
        return false;
    }
}
