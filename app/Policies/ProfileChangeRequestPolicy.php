<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\ProfileChangeRequest;
use App\Models\User;

class ProfileChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProfileChangeRequest $request): bool
    {
        return $user->isHr() || $this->belongsTo($user, $request);
    }

    /** An employee needs a linked personnel record to have anything to change. */
    public function create(User $user): bool
    {
        return $user->isHr() || $user->personnel()->exists();
    }

    public function update(User $user, ProfileChangeRequest $request): bool
    {
        return $this->belongsTo($user, $request)
            && $request->status->isEditableByEmployee();
    }

    public function delete(User $user, ProfileChangeRequest $request): bool
    {
        return $this->belongsTo($user, $request)
            && $request->status === RequestStatus::Draft;
    }

    /**
     * A request the employee deleted while it was still a draft can be put
     * back by that employee, or by HR who can see the whole queue.
     */
    public function restore(User $user, ProfileChangeRequest $request): bool
    {
        return $user->isHr() || $this->belongsTo($user, $request);
    }

    /**
     * Permanent deletion is deliberately not offered: a request and its
     * history are the audit trail for a change to the personnel record.
     */
    public function forceDelete(User $user, ProfileChangeRequest $request): bool
    {
        return false;
    }

    public function submit(User $user, ProfileChangeRequest $request): bool
    {
        return $this->belongsTo($user, $request)
            && $request->status->isEditableByEmployee()
            && $request->items()->exists();
    }

    /** HR Staff verify; this is the step that unlocks a decision. */
    public function verify(User $user, ProfileChangeRequest $request): bool
    {
        return $user->isHrStaff() && $request->isAwaitingVerification();
    }

    /** Only HR Approver decides, and only on a verified request. */
    public function decide(User $user, ProfileChangeRequest $request): bool
    {
        return $user->isHrApprover() && $request->isAwaitingDecision();
    }

    /**
     * HR Staff can send a request back without escalating it; the approver can
     * return one that reached them.
     */
    public function returnForRevision(User $user, ProfileChangeRequest $request): bool
    {
        if ($request->status !== RequestStatus::Pending) {
            return false;
        }

        return $user->isHrStaff() || ($user->isHrApprover() && $request->isVerified());
    }

    private function belongsTo(User $user, ProfileChangeRequest $request): bool
    {
        return $request->submitted_by === $user->id
            || $request->personnel?->user_id === $user->id;
    }
}
