<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\ProfileChangeHistory;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Support\ProfileField;
use Illuminate\Support\Facades\DB;

/**
 * Every status transition of a Profile Change Request goes through here, so
 * the audit trail can never fall out of step with the request itself.
 */
class ProfileChangeWorkflow
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function submit(ProfileChangeRequest $request, User $actor): ProfileChangeRequest
    {
        return $this->transition($request, RequestStatus::Pending, $actor, 'Submitted', null, [
            'submitted_by' => $request->submitted_by ?? $actor->id,
            'submitted_at' => now(),
            // A resubmission starts the review over.
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_remarks' => null,
        ]);
    }

    /** HR Staff verification. The status stays Pending; only the decision gate opens. */
    public function verify(ProfileChangeRequest $request, User $actor, ?string $remarks = null): ProfileChangeRequest
    {
        $request->forceFill([
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
            'updated_by' => $actor->id,
        ])->save();

        $this->record($request, $actor, 'Verified by HR Staff', RequestStatus::Pending, RequestStatus::Pending, $remarks);

        return $request->refresh();
    }

    public function returnForRevision(ProfileChangeRequest $request, User $actor, string $remarks): ProfileChangeRequest
    {
        return $this->transition($request, RequestStatus::Returned, $actor, 'Returned for revision', $remarks, [
            'decision_remarks' => $remarks,
        ]);
    }

    public function reject(ProfileChangeRequest $request, User $actor, string $remarks): ProfileChangeRequest
    {
        return $this->transition($request, RequestStatus::Rejected, $actor, 'Rejected', $remarks, [
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_remarks' => $remarks,
        ]);
    }

    /**
     * Approve the request and write its items onto the personnel record.
     *
     * An office or position change is applied as an appointment transfer, not
     * an in-place edit, so the one-active-primary rule is exercised on the
     * real path rather than bypassed.
     */
    public function approve(ProfileChangeRequest $request, User $actor, ?string $remarks = null): ProfileChangeRequest
    {
        return DB::transaction(function () use ($request, $actor, $remarks) {
            $personnel = $request->personnel()->lockForUpdate()->first();
            $items = $request->items()->get();

            $personalChanges = $items
                ->reject(fn ($item) => ProfileField::isAssignment($item->field))
                ->mapWithKeys(fn ($item) => [$item->field => $item->new_value]);

            if ($personalChanges->isNotEmpty()) {
                $personnel->fill($personalChanges->all())->save();
            }

            $assignment = $items->filter(fn ($item) => ProfileField::isAssignment($item->field));

            if ($assignment->isNotEmpty()) {
                $current = $personnel->activePrimaryAppointment;

                $officeId = (int) ($assignment->firstWhere('field', 'office_id')?->new_value ?? $current?->office_id);
                $positionId = (int) ($assignment->firstWhere('field', 'position_id')?->new_value ?? $current?->position_id);

                $this->appointments->transferPrimary(
                    $personnel,
                    $officeId,
                    $positionId,
                    "Per approved request {$request->reference_no}",
                );
            }

            return $this->transition($request, RequestStatus::Approved, $actor, 'Approved and applied', $remarks, [
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_remarks' => $remarks,
            ]);
        });
    }

    private function transition(
        ProfileChangeRequest $request,
        RequestStatus $to,
        User $actor,
        string $action,
        ?string $remarks,
        array $attributes = [],
    ): ProfileChangeRequest {
        $from = $request->status;

        // updated_by comes from the actor rather than the session: the
        // workflow already knows who is acting, and it is also driven from
        // tests and seeders that have no session at all.
        $request->forceFill($attributes + ['status' => $to, 'updated_by' => $actor->id])->save();

        $this->record($request, $actor, $action, $from, $to, $remarks);

        return $request->refresh();
    }

    private function record(
        ProfileChangeRequest $request,
        ?User $actor,
        string $action,
        ?RequestStatus $from,
        ?RequestStatus $to,
        ?string $remarks,
    ): void {
        ProfileChangeHistory::create([
            'profile_change_request_id' => $request->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }
}
