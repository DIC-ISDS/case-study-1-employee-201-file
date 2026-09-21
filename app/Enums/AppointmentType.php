<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentType: string implements HasColor, HasLabel
{
    case Primary = 'primary';
    case Secondary = 'secondary';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return $this === self::Primary ? 'success' : 'gray';
    }
}
