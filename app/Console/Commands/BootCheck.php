<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Runtime\ProductionBootCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Refuses to start an unsafe production container (TE-012 AC8): exits 1 with one clear message per problem.
 * Run by the production entrypoint's `app`, `worker` and `scheduler` roles before the DB wait (docker/prod/entrypoint.sh).
 */
#[Signature('traveleasy:boot-check')]
#[Description('Refuse to start when the production configuration is unsafe (APP_DEBUG, APP_KEY, TRUSTED_PROXIES)')]
final class BootCheck extends Command
{
    public function handle(): int
    {
        $violations = ProductionBootCheck::fromConfig()->violations();

        if ($violations === []) {
            $this->components->info('Boot check passed.');

            return self::SUCCESS;
        }

        foreach ($violations as $violation) {
            $this->components->error('Refusing to start: '.$violation);
        }

        return self::FAILURE;
    }
}
