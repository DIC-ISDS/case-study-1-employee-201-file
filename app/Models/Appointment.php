<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Concerns\TracksOwnership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes, TracksOwnership;

    protected $fillable = [
        'personnel_id', 'office_id', 'position_id', 'type', 'status',
        'start_date', 'end_date', 'remarks',
    ];

    /**
     * Written by MySQL, never by the application: it exists so the unique
     * index can enforce one active primary appointment per employee.
     */
    protected $guarded = ['active_primary_personnel_id'];

    protected function casts(): array
    {
        return [
            'type' => AppointmentType::class,
            'status' => AppointmentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * CORE BUSINESS RULE: an employee may hold only one active primary
     * appointment at a time.
     *
     * Guarded here rather than only in the Filament form so that seeders,
     * tinker, tests and the approval step are all held to it. The unique
     * index on appointments.active_primary_personnel_id is the backstop; this
     * hook exists to fail with a message a user can act on instead of an
     * integrity-constraint error.
     */
    protected static function booted(): void
    {
        static::saving(function (self $appointment) {
            if (! $appointment->isActivePrimary()) {
                return;
            }

            $conflict = static::query()
                ->activePrimary()
                ->where('personnel_id', $appointment->personnel_id)
                ->when($appointment->exists, fn ($query) => $query->whereKeyNot($appointment->getKey()))
                ->with(['office', 'position'])
                ->first();

            if ($conflict === null) {
                return;
            }

            throw BusinessRuleViolation::duplicateActivePrimaryAppointment(
                $appointment->personnel?->full_name ?? 'This employee',
                trim(($conflict->position?->title ?? 'Unknown position').' — '.($conflict->office?->name ?? 'Unknown office')),
            );
        });
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function scopeActivePrimary(Builder $query): Builder
    {
        return $query->where('type', AppointmentType::Primary)
            ->where('status', AppointmentStatus::Active);
    }

    public function isActivePrimary(): bool
    {
        return $this->type === AppointmentType::Primary
            && $this->status === AppointmentStatus::Active;
    }
}
