<?php

declare(strict_types=1);

namespace App\Support\Console;

/**
 * Whether the console has a real terminal on standard input (TE-025 AC5): hidden password prompts need one, so the
 * secrets helper fails clearly under `docker run` without `-it`, in a pipe or in CI instead of reading a password
 * from somewhere visible. Bound in the container so tests can replace it.
 */
class Terminal
{
    public function hasInteractiveInput(): bool
    {
        return defined('STDIN') && stream_isatty(STDIN);
    }
}
