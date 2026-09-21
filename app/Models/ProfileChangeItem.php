<?php

namespace App\Models;

use App\Models\Concerns\TracksOwnership;
use App\Support\ProfileField;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfileChangeItem extends Model
{
    use HasFactory, SoftDeletes, TracksOwnership;

    protected $fillable = ['profile_change_request_id', 'field', 'old_value', 'new_value'];

    /**
     * Snapshot what the field held when the line was raised. Done here rather
     * than in the form so the "from -> to" record is right no matter how the
     * item was created, and so it cannot be spoofed from the request payload.
     */
    protected static function booted(): void
    {
        static::creating(function (self $item) {
            // An explicitly supplied old_value wins -- historical records
            // (and seeded history) must keep the value as it was then, not as
            // it is now.
            if (array_key_exists('old_value', $item->getAttributes())) {
                return;
            }

            $personnel = $item->request?->personnel;

            if ($personnel !== null) {
                $item->old_value = ProfileField::currentValue($personnel, $item->field);
            }
        });
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProfileChangeRequest::class, 'profile_change_request_id');
    }

    public function getFieldLabelAttribute(): string
    {
        return ProfileField::label($this->field);
    }

    public function getOldDisplayAttribute(): string
    {
        return ProfileField::display($this->field, $this->old_value);
    }

    public function getNewDisplayAttribute(): string
    {
        return ProfileField::display($this->field, $this->new_value);
    }
}
