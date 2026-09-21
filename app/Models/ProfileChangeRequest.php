<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\TracksOwnership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfileChangeRequest extends Model
{
    use HasFactory, SoftDeletes, TracksOwnership;

    protected $fillable = [
        'reference_no', 'personnel_id', 'submitted_by', 'status', 'purpose',
        'reviewed_by', 'reviewed_at', 'review_remarks',
        'decided_by', 'decided_at', 'decision_remarks', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'reviewed_at' => 'datetime',
            'decided_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            $request->reference_no ??= static::nextReferenceNo();
        });
    }

    public static function nextReferenceNo(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;

        // Loop past any reference already taken, so a deleted-and-recreated
        // request cannot collide with an existing one.
        do {
            $reference = sprintf('PCR-%d-%04d', $year, $count);
            $count++;
        } while (static::where('reference_no', $reference)->exists());

        return $reference;
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProfileChangeItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ProfileChangeHistory::class)->latest('created_at');
    }

    public function isVerified(): bool
    {
        return $this->reviewed_at !== null;
    }

    /** A request is only decidable once HR Staff has verified it. */
    public function isAwaitingDecision(): bool
    {
        return $this->status === RequestStatus::Pending && $this->isVerified();
    }

    public function isAwaitingVerification(): bool
    {
        return $this->status === RequestStatus::Pending && ! $this->isVerified();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Pending);
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Returned);
    }
}
