<?php

declare(strict_types=1);

namespace App\Services\Erasure;

use App\Models\User;

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
        $identifier = config('auth.super_admin_email');

        if (! is_string($identifier) || trim($identifier) === '') {
            return false;
        }

        return mb_strtolower(trim($identifier)) === mb_strtolower(trim($user->email));
    }
}
