<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\EmploymentStatus;
use App\Models\Concerns\TracksOwnership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Personnel extends Model
{
    use HasFactory, SoftDeletes, TracksOwnership;

    protected $table = 'personnel';

    protected $fillable = [
        'employee_no', 'last_name', 'first_name', 'middle_name', 'email',
        'contact_number', 'address', 'birth_date', 'employment_status', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'employment_status' => EmploymentStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * The one appointment that defines this employee's current office and
     * position. The database guarantees there is at most one.
     */
    public function activePrimaryAppointment(): HasOne
    {
        return $this->hasOne(Appointment::class)
            ->where('type', AppointmentType::Primary)
            ->where('status', AppointmentStatus::Active);
    }

    public function profileChangeRequests(): HasMany
    {
        return $this->hasMany(ProfileChangeRequest::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} ".($this->middle_name ? substr($this->middle_name, 0, 1).'. ' : '').$this->last_name);
    }

    public function getFullNameLastFirstAttribute(): string
    {
        return trim("{$this->last_name}, {$this->first_name}");
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', '!=', EmploymentStatus::Resigned);
    }
}
