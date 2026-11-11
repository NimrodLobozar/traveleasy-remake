<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AccountEraser;
use App\Services\Erasure\ErasureOutcome;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Throwable;

/**
 * Erases user accounts by ID (TE-047): one ID as the argument, or one ID per line from a file or stdin (`-`), for
 * example the ID list of a re-erasure after a database restore. Each ID is erased in its own transaction, so one
 * failure never stops the others. Output and log carry only IDs and outcomes: never a name, an e-mail or the content
 * of a rejected line, and never an exception message (a query exception's message can contain bound values).
 *
 * Exit code 0 only when nothing failed or was refused.
 */
#[Signature('users:erase
    {id? : The ID of the user to erase}
    {--from-file= : A file with one user ID per line, or - to read from stdin}')]
#[Description('Erase user accounts by ID (idempotent; safe to re-run after a restore)')]
final class EraseUsers extends Command
{
    private int $erased = 0;

    private int $notFound = 0;

    private int $refused = 0;

    private int $failed = 0;

    public function handle(AccountEraser $eraser): int
    {
        // The console application can reuse one command instance for several runs.
        $this->erased = $this->notFound = $this->refused = $this->failed = 0;

        $id = $this->argument('id');
        $file = $this->option('from-file');

        if (is_string($id) === is_string($file)) {
            $this->components->error('Pass either one user ID or --from-file=<path> (use - for stdin).');

            return self::INVALID;
        }

        if (is_string($id)) {
            $this->eraseLine($eraser, $id, null);

            return $this->exitCode();
        }

        $stream = $this->openInput((string) $file);
        if ($stream === null) {
            $this->components->error('The ID file cannot be read.');

            return self::INVALID;
        }

        $lineNumber = 0;
        while (($line = fgets($stream)) !== false) {
            $lineNumber++;
            $this->eraseLine($eraser, $line, $lineNumber);
        }

        if ($file !== '-') {
            fclose($stream);
        }

        $this->line(sprintf(
            'Summary: %d erased, %d not found, %d refused, %d failed.',
            $this->erased,
            $this->notFound,
            $this->refused,
            $this->failed,
        ));

        return $this->exitCode();
    }

    private function eraseLine(AccountEraser $eraser, string $raw, ?int $lineNumber): void
    {
        $userId = filter_var(trim($raw), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($userId === false) {
            $this->failed++;
            $this->line($lineNumber === null ? 'FAILED (not a user ID)' : "line {$lineNumber} FAILED (not a user ID)");

            return;
        }

        try {
            $outcome = $eraser->eraseById($userId, AccountEraser::ACTOR_CLI);
        } catch (Throwable $e) {
            $this->failed++;
            $this->line("{$userId} FAILED");
            Log::error('Account erasure failed; nothing was changed for this user.', [
                'user_id' => $userId,
                'exception' => $e::class,
            ]);

            return;
        }

        match ($outcome) {
            ErasureOutcome::Erased => $this->erased++,
            ErasureOutcome::NotFound => $this->notFound++,
            ErasureOutcome::Refused => $this->refused++,
        };

        $this->line("{$userId} {$outcome->value}");
    }

    /**
     * @return resource|null
     */
    private function openInput(string $path)
    {
        if ($path === '-') {
            $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;

            return $stream ?? STDIN;
        }

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $stream = fopen($path, 'rb');

        return $stream === false ? null : $stream;
    }

    private function exitCode(): int
    {
        return $this->failed + $this->refused > 0 ? self::FAILURE : self::SUCCESS;
    }
}
