<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * All three roles use the same panel; what differs is what each one may
     * see and do, which the policies decide.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role instanceof UserRole;
    }

    /** The personnel record this login belongs to, if any. */
    public function personnel(): HasOne
    {
        return $this->hasOne(Personnel::class);
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::Employee;
    }

    public function isHrStaff(): bool
    {
        return $this->role === UserRole::HrStaff;
    }

    public function isHrApprover(): bool
    {
        return $this->role === UserRole::HrApprover;
    }

    public function isHr(): bool
    {
        return $this->role?->isHr() ?? false;
    }
}
