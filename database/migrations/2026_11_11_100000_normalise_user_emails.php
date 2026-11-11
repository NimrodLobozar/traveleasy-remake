<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Trim and lowercase users.email, then enforce case-insensitive uniqueness.
     *
     * The original unique(email) index is kept on purpose: it is implied by the
     * lower(email) index for integrity, but it still serves the exact-match
     * lookups (login, unique rules) that the lower() index cannot.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $collisions = DB::table('users')
                ->selectRaw('lower(trim(email)) as normalised')
                ->groupByRaw('lower(trim(email))')
                ->havingRaw('count(*) > 1')
                ->count();

            if ($collisions > 0) {
                throw new RuntimeException(
                    "Cannot normalise user e-mails: {$collisions} group(s) of accounts collide after trim and lowercase. "
                    .'Nothing was changed. Resolve the duplicates by hand (query: group users by lower(trim(email))), then migrate again.'
                );
            }

            DB::statement('UPDATE users SET email = lower(trim(email)) WHERE email <> lower(trim(email))');
            DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');
        });
    }

    /** Data normalisation is not reversible; only the index is removed. */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
