<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The authenticated user as shared with every Inertia page (`auth.user`).
 *
 * Only the fields the UI needs. Never add the password hash, remember token,
 * 2FA secrets or any personal data beyond what the shell displays.
 */
final class SharedUserData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        /** Not stored yet; kept optional so the starter-kit avatar components type-check. */
        public readonly string|Optional $avatar,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            avatar: Optional::create(),
        );
    }
}
