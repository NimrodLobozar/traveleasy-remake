<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one normalisation for e-mail addresses (TE-049): surrounding whitespace trimmed, lowercased.
 *
 * Storage (the `User::email` mutator), validation, login and the protected-account check all use this, so an address
 * that differs only in case or surrounding spaces is the same login name everywhere. The database enforces the same
 * with a unique index on `lower(email)`.
 */
final class EmailAddress
{
    public static function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
