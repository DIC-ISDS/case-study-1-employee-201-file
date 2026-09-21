<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RequestStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Returned => 'info',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::Pending => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Returned => 'heroicon-o-arrow-uturn-left',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }

    /** Draft and Returned are the two states an employee may still edit. */
    public function isEditableByEmployee(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }
}
