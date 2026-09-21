<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileChangeHistory extends Model
{
    use HasFactory;

    protected $table = 'profile_change_histories';

    public $timestamps = false;

    protected $fillable = [
        'profile_change_request_id', 'actor_id', 'action',
        'from_status', 'to_status', 'remarks', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'from_status' => RequestStatus::class,
            'to_status' => RequestStatus::class,
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProfileChangeRequest::class, 'profile_change_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
