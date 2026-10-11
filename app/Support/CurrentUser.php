<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * Resolves the authenticated user of a request as a non-null User.
 *
 * Routes that need a user sit behind the `auth` middleware, so a missing user here means a route was wired
 * without it. Deny by default: treat that as unauthenticated instead of failing later on a null.
 */
final class CurrentUser
{
    /**
     * @throws AuthenticationException
     */
    public static function of(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
