<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use App\Services\Erasure\ProtectedAccountGuard;
use App\Support\EmailAddress;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Case-insensitive uniqueness of a user's e-mail address (TE-049).
 *
 * Compares on `lower(email)`, so it is backed by the `users_email_lower_unique` index and also catches a row stored in
 * another case before normalisation was in place. The protected super_admin address counts as taken even before the
 * account exists, so a case variant cannot claim it. The ignored user (own profile) may keep their own address in any
 * case or with surrounding spaces. The message is the existing `validation.unique` key: it names no address.
 */
final readonly class UniqueEmail implements ValidationRule
{
    public function __construct(private ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $email = EmailAddress::normalise($value);

        $taken = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->when($this->ignoreUserId !== null, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->exists();

        if ($taken || $this->isReservedForSomeoneElse($email)) {
            $fail('validation.unique')->translate();
        }
    }

    private function isReservedForSomeoneElse(string $email): bool
    {
        if (! app(ProtectedAccountGuard::class)->isProtectedAddress($email)) {
            return false;
        }

        if ($this->ignoreUserId === null) {
            return true;
        }

        return ! User::query()
            ->whereKey($this->ignoreUserId)
            ->whereRaw('lower(email) = ?', [$email])
            ->exists();
    }
}
