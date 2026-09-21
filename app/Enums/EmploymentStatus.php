<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmploymentStatus: string implements HasColor, HasLabel
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case Casual = 'casual';
    case Contractual = 'contractual';
    case Resigned = 'resigned';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Permanent => 'success',
            self::Temporary, self::Contractual => 'info',
            self::Casual => 'warning',
            self::Resigned => 'danger',
        };
    }

    /** Resigned personnel are excluded from "active personnel" counts. */
    public function isActive(): bool
    {
        return $this !== self::Resigned;
    }
}
