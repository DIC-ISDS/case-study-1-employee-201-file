<?php

namespace App\Support;

use App\Enums\EmploymentStatus;
use App\Models\Office;
use App\Models\Personnel;
use App\Models\Position;

/**
 * The profile fields an employee may request a change to, and how each one is
 * labelled and rendered. Keeping this in one place means the request form, the
 * comparison table and the approval step cannot drift apart.
 */
class ProfileField
{
    /** Fields stored directly on the personnel record. */
    public const PERSONAL = [
        'last_name' => 'Last name',
        'first_name' => 'First name',
        'middle_name' => 'Middle name',
        'contact_number' => 'Contact number',
        'address' => 'Address',
        'employment_status' => 'Employment status',
    ];

    /** Fields that resolve to the employee's active primary appointment. */
    public const ASSIGNMENT = [
        'office_id' => 'Office',
        'position_id' => 'Position',
    ];

    public static function all(): array
    {
        return self::PERSONAL + self::ASSIGNMENT;
    }

    public static function label(string $field): string
    {
        return self::all()[$field] ?? str($field)->headline()->toString();
    }

    public static function isAssignment(string $field): bool
    {
        return array_key_exists($field, self::ASSIGNMENT);
    }

    /** The employee's current value for a field, as stored. */
    public static function currentValue(Personnel $personnel, string $field): ?string
    {
        $appointment = $personnel->activePrimaryAppointment;

        return match ($field) {
            'office_id' => $appointment?->office_id === null ? null : (string) $appointment->office_id,
            'position_id' => $appointment?->position_id === null ? null : (string) $appointment->position_id,
            'employment_status' => $personnel->employment_status?->value,
            default => $personnel->{$field} === null ? null : (string) $personnel->{$field},
        };
    }

    /** A stored value rendered for a human reader. */
    public static function display(string $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'office_id' => Office::find($value)?->name ?? "Office #{$value}",
            'position_id' => Position::find($value)?->title ?? "Position #{$value}",
            'employment_status' => EmploymentStatus::tryFrom($value)?->getLabel() ?? $value,
            default => $value,
        };
    }
}
