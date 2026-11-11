<?php

declare(strict_types=1);

namespace App\Services\Erasure;

use App\Models\User;
use App\Support\EmailAddress;

/**
 * The one place that decides whether an account may never be erased.
 *
 * Until the roles story (M1) adds the `super_admin` role, the protected account is recognised by the env-provisioned
 * identifier `SUPER_ADMIN_EMAIL` (config `auth.super_admin_email`; owner decision 2026-11-11). The roles story must
 * replace this with the real role check. Without the env value there is no super_admin and nothing is protected.
 */
class ProtectedAccountGuard
{
    public function isProtected(User $user): bool
    {
        return $this->isProtectedAddress($user->email);
    }

    /**
     * Whether the address is the protected one, compared in the shared normalised form ({@see EmailAddress}), so a
     * case variant or surrounding spaces on either side still match. Also stops registration claiming the address.
     */
    public function isProtectedAddress(string $email): bool
    {
        $identifier = config('auth.super_admin_email');

        if (! is_string($identifier) || EmailAddress::normalise($identifier) === '') {
            return false;
        }

        return EmailAddress::normalise($identifier) === EmailAddress::normalise($email);
    }
}
