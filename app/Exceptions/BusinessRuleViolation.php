<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an operation would break a core business rule. Filament catches
 * this and shows the message to the user instead of an error page.
 */
class BusinessRuleViolation extends RuntimeException
{
    public static function duplicateActivePrimaryAppointment(string $employee, string $existing): self
    {
        return new self(
            "{$employee} already holds an active primary appointment ({$existing}). "
            .'An employee may have only one active primary appointment at a time — '
            .'end the current one first, or file this as a secondary appointment.'
        );
    }
}
