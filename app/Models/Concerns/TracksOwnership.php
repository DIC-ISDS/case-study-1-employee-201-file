<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Record ownership for the hackathon MVP: who created a record and who last
 * touched it.
 *
 * Both columns are filled from the authenticated user here rather than in the
 * Filament forms, which is what makes them un-spoofable -- there is no
 * created_by input to tamper with, and seeders, tinker and the approval
 * workflow are held to the same rule as the UI.
 *
 * updated_by stays null until a record is actually changed, so "never touched
 * since it was filed" is visible at a glance.
 */
trait TracksOwnership
{
    protected static function bootTracksOwnership(): void
    {
        static::creating(function (self $model) {
            // ??= rather than = : seeded and factory-built records may name
            // their owner explicitly, and a test or import has no session.
            $model->created_by ??= Auth::id();
        });

        static::updating(function (self $model) {
            if (Auth::hasUser()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Records raised by one user -- the ownership half of user-scoped tenancy. */
    public function scopeOwnedBy(Builder $query, User|int|null $user): Builder
    {
        return $query->where('created_by', $user instanceof User ? $user->id : $user);
    }
}
