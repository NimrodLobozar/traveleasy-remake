<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Erasure\ErasureOutcome;
use App\Services\Erasure\PersonalDataEraser;
use App\Services\Erasure\ProtectedAccountGuard;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Support\Facades\DB;

/**
 * Erases a user account and every row of personal data tied to it (TE-047). Used by self-service deletion
 * (`ProfileController::destroy`) and the `users:erase` command; there is no HTTP route to erase another user.
 *
 * - One transaction per user, with the user row locked. Registered erasers run first, then the core tables
 *   (`sessions`: no foreign key, so deleted by name; `passkeys`: cascades, deleted explicitly anyway), then the user
 *   row and finally the audit entry. Any exception rolls back everything for that user. There is no
 *   `password_reset_tokens` table (no reset by mail, ADR-0005; `NoMailFlowsTest`).
 * - Registry: a story that adds a table with personal data (persons, travellers, messages, invoices, ...) implements
 *   {@see PersonalDataEraser} and tags it in a service provider:
 *   `$this->app->tag([PersonEraser::class], AccountEraser::ERASER_TAG);`, with a test that the eraser runs.
 * - Invoices are never deleted: they are retained 7 years (fiscal retention) and their eraser anonymises the personal
 *   fields instead (see `docs/privacy/data-inventory.md`).
 * - Protected accounts (the `super_admin`) are refused; the check lives in {@see ProtectedAccountGuard}.
 * - The audit entry holds only the event, the pseudonymous user ID and the actor; never a name or e-mail.
 */
class AccountEraser
{
    public const ERASER_TAG = 'account.erasers';

    public const AUDIT_EVENT = 'account_erased';

    public const ACTOR_CLI = 'cli';

    public const ACTOR_SELF = 'self';

    /** @var list<PersonalDataEraser> */
    private readonly array $erasers;

    /**
     * @param  iterable<PersonalDataEraser>  $erasers
     */
    public function __construct(
        private readonly ProtectedAccountGuard $guard,
        #[Tag(self::ERASER_TAG)] iterable $erasers = [],
    ) {
        $list = [];
        foreach ($erasers as $eraser) {
            $list[] = $eraser;
        }
        $this->erasers = $list;
    }

    public function isProtected(User $user): bool
    {
        return $this->guard->isProtected($user);
    }

    /**
     * Erase the user with this ID. Idempotent: a missing ID changes nothing.
     */
    public function eraseById(int $userId, string $actor): ErasureOutcome
    {
        return DB::transaction(function () use ($userId, $actor): ErasureOutcome {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();

            if ($user === null) {
                return ErasureOutcome::NotFound;
            }

            if ($this->guard->isProtected($user)) {
                return ErasureOutcome::Refused;
            }

            $this->eraseLocked($user, $actor);

            return ErasureOutcome::Erased;
        });
    }

    public function erase(User $user, string $actor): ErasureOutcome
    {
        return $this->eraseById($user->id, $actor);
    }

    private function eraseLocked(User $user, string $actor): void
    {
        $userId = $user->id;

        foreach ($this->erasers as $eraser) {
            $eraser->erase($user);
        }

        DB::table('sessions')->where('user_id', $userId)->delete();
        DB::table('passkeys')->where('user_id', $userId)->delete();

        $user->delete();

        AuditLog::query()->create([
            'event' => self::AUDIT_EVENT,
            'subject_user_id' => $userId,
            'actor' => $actor,
        ]);
    }
}
