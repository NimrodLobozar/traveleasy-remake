<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Seeds the production reference data (airports, roles) into tables that are still empty (ADR-0002 D4).
 *
 * The production entrypoint runs this on every `app` start. It is a no-op in M0 because no reference table exists
 * yet; the database-engineer adds each table's seeder here when the table lands. Never seeds `demo` data.
 */
#[Signature('traveleasy:seed-reference {--if-empty : Seed a reference table only when it is empty (always the case; staff edits are never overwritten)}')]
#[Description('Seed reference data into empty tables (safe to run on every start)')]
final class SeedReference extends Command
{
    public function handle(): int
    {
        $this->components->info('No reference tables yet; nothing to seed.');

        return self::SUCCESS;
    }
}
