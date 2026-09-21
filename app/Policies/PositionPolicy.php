<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isHr();
    }

    public function view(User $user, Position $position): bool
    {
        return $user->isHr();
    }

    public function create(User $user): bool
    {
        return $user->isHr();
    }

    public function update(User $user, Position $position): bool
    {
        return $user->isHr();
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->isHrApprover();
    }

    /**
     * Whoever may delete master data may put it back.
     */
    public function restore(User $user, Position $position): bool
    {
        return $user->isHrApprover();
    }

    /**
     * Permanent deletion is deliberately not offered: a soft-deleted record is
     * still part of the audit history, and nothing in the MVP needs it gone.
     */
    public function forceDelete(User $user, Position $position): bool
    {
        return false;
    }
}
