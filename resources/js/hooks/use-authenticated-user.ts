import { usePage } from '@inertiajs/react';
import type { SharedUserData } from '@/types/generated';

/**
 * The signed-in user, for pages that are only served behind the `auth` middleware.
 *
 * This only narrows the type of the shared `auth.user` prop; it is not an access check.
 * The server decides who may see a page. If a guest ever reaches a page that calls this,
 * that is a routing bug, so it fails loudly instead of rendering with a missing user.
 */
export function useAuthenticatedUser(): SharedUserData {
    const { user } = usePage().props.auth;

    if (user === null) {
        throw new Error(
            'useAuthenticatedUser() was called on a page without a signed-in user.',
        );
    }

    return user;
}
