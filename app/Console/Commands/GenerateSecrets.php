<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Console\Terminal;
use App\Support\Console\TerminalQrCode;
use App\Support\Deploy\SecretsGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints the five production secrets as ready-to-paste `KEY=value` lines for the Portainer env file (TE-025,
 * ADR-0002 D5, docs/deploy/casaos-portainer.md):
 *
 *   docker run --rm -it ghcr.io/nimrodlobozar/traveleasy:<tag> php artisan traveleasy:generate-secrets
 *
 * - Runs through the production entrypoint's pass-through: no database, no boot check, no network.
 * - Writes nothing to disk and logs nothing; the values exist only on the terminal.
 * - The super_admin password is read with a hidden prompt, never from an argument or env, so it never reaches the shell
 *   history. Without a terminal (no `-it`, a pipe, CI) the command stops before printing anything.
 * - Refuses to run when CI=true (the values would land in a CI log) unless `--force-ci` is given.
 * - `--without-super-admin` prints only the two DB passwords and APP_KEY, with no prompt.
 *
 * Output is in English (operator tool).
 */
#[Signature('traveleasy:generate-secrets
    {--without-super-admin : Only DB_SUPERUSER_PASSWORD, DB_PASSWORD and APP_KEY (no prompt, no terminal needed)}
    {--light-terminal : Draw the QR code for a terminal with a light background}
    {--force-ci : Print the secrets even when CI=true (they end up in the CI log)}')]
#[Description('Print the production secrets for the Portainer env file (nothing is stored, logged or sent)')]
final class GenerateSecrets extends Command
{
    public const int PASSWORD_MIN_LENGTH = 12;

    /** bcrypt only uses the first 72 bytes. */
    public const int PASSWORD_MAX_BYTES = 72;

    private const int PASSWORD_ATTEMPTS = 3;

    public function handle(SecretsGenerator $secrets, Terminal $terminal): int
    {
        if (config()->boolean('app.ci') && $this->option('force-ci') !== true) {
            $this->components->error('CI=true: refusing to print secrets into a CI log. Run this on your own machine, or add --force-ci.');

            return self::FAILURE;
        }

        $withSuperAdmin = $this->option('without-super-admin') !== true;

        if ($withSuperAdmin && (! $this->input->isInteractive() || ! $terminal->hasInteractiveInput())) {
            $this->components->error(
                'The super_admin password is read with a hidden prompt, which needs an interactive terminal. Run '
                .'`docker run --rm -it <image> php artisan traveleasy:generate-secrets` (note -it), or add '
                .'--without-super-admin to print only the database passwords and APP_KEY. Passwords are never '
                .'accepted as arguments or environment variables.',
            );

            return self::FAILURE;
        }

        $values = [
            'DB_SUPERUSER_PASSWORD' => $secrets->databasePassword(),
            'DB_PASSWORD' => $secrets->databasePassword(),
            'APP_KEY' => $secrets->appKey(),
        ];
        $totpSecret = null;

        if ($withSuperAdmin) {
            $password = $this->askNewPassword();

            if ($password === null) {
                $this->components->error('No valid password after '.self::PASSWORD_ATTEMPTS.' attempts; nothing was printed.');

                return self::FAILURE;
            }

            $totpSecret = $secrets->totpSecret();
            $values['SUPER_ADMIN_PASSWORD_HASH'] = $secrets->passwordHash($password);
            $values['SUPER_ADMIN_TOTP_SECRET'] = $totpSecret;
        }

        if ($totpSecret !== null) {
            $this->printTotp($secrets->totpUri($totpSecret), $totpSecret);
        }

        $this->printEnvLines($values);

        return self::SUCCESS;
    }

    /**
     * Ask twice with hidden input; a few attempts, then give up.
     */
    private function askNewPassword(): ?string
    {
        $this->line('Choose the super_admin password (hidden input). Use at least '.self::PASSWORD_MIN_LENGTH
            .' characters with upper and lower case, a number and a symbol; a password manager helps.');

        for ($attempt = 1; $attempt <= self::PASSWORD_ATTEMPTS; $attempt++) {
            $password = $this->secret('super_admin password');
            $password = is_string($password) ? $password : '';
            $problem = self::passwordProblem($password);

            if ($problem === null) {
                $confirmation = $this->secret('Repeat the password');

                if ($confirmation === $password) {
                    return $password;
                }

                $problem = 'The two passwords differ.';
            }

            $this->components->warn($problem);
        }

        return null;
    }

    /**
     * Why a password is not acceptable, or null. Checked offline: no breach lookup (the helper has no network).
     */
    public static function passwordProblem(#[\SensitiveParameter] string $password): ?string
    {
        return match (true) {
            mb_strlen($password) < self::PASSWORD_MIN_LENGTH => 'Use at least '.self::PASSWORD_MIN_LENGTH.' characters.',
            strlen($password) > self::PASSWORD_MAX_BYTES => 'Use at most '.self::PASSWORD_MAX_BYTES.' bytes (bcrypt ignores the rest).',
            preg_match('/\p{Lu}/u', $password) !== 1 || preg_match('/\p{Ll}/u', $password) !== 1 => 'Use upper and lower case letters.',
            preg_match('/\p{N}/u', $password) !== 1 => 'Use at least one number.',
            preg_match('/[^\p{L}\p{N}]/u', $password) !== 1 => 'Use at least one symbol.',
            default => null,
        };
    }

    private function printTotp(#[\SensitiveParameter] string $uri, #[\SensitiveParameter] string $secret): void
    {
        $this->newLine();
        $this->line('Scan this QR code with your authenticator app (account "super_admin"):');
        $this->newLine();

        foreach (TerminalQrCode::render($uri, forLightBackground: $this->option('light-terminal') === true) as $line) {
            $this->output->writeln($line, OutputInterface::OUTPUT_RAW);
        }

        $this->newLine();
        $this->line('Or enter this secret by hand (time-based, SHA1, 6 digits, 30 s): '.$secret);
        $this->line('If the code does not scan, try --light-terminal or enlarge the window.');
    }

    /**
     * @param  array<string, string>  $values
     */
    private function printEnvLines(array $values): void
    {
        $this->newLine();
        $this->line('Paste these lines into the Portainer stack (they replace the empty SECRET lines of deploy/portainer.env.example).');
        $this->line('Keep them in your password manager; they are not stored anywhere else.');
        $this->newLine();

        foreach ($values as $key => $value) {
            $this->output->writeln("{$key}={$value}", OutputInterface::OUTPUT_RAW);
        }

        $this->newLine();
    }
}
