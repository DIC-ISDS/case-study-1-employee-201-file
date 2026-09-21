<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Employee = 'employee';
    case HrStaff = 'hr_staff';
    case HrApprover = 'hr_approver';

    public function getLabel(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::HrStaff => 'HR Staff',
            self::HrApprover => 'HR Approver',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Employee => 'gray',
            self::HrStaff => 'info',
            self::HrApprover => 'warning',
        };
    }

    /** HR Staff and HR Approver both administer personnel master data. */
    public function isHr(): bool
    {
        return $this !== self::Employee;
    }
}
