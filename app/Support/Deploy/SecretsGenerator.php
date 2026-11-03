<?php

declare(strict_types=1);

namespace App\Support\Deploy;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Makes the production secrets for the Portainer env file (TE-025, ADR-0002 D5). Everything comes from the CSPRNG
 * (random_bytes / random_int); nothing is stored, logged or sent anywhere.
 *
 * Every value is safe as an unquoted `KEY=value` line in Portainer and in docker compose `.env` parsing: only
 * `[A-Za-z0-9_-]`, or `base64:` + `[A-Za-z0-9+/=]`. No `$` (compose would interpolate it), `#`, quotes or spaces.
 */
final readonly class SecretsGenerator
{
    /** Random bytes per DB password: 256 bits, 43 URL-safe characters. */
    public const int PASSWORD_BYTES = 32;

    /** Base32 characters of the TOTP secret: 160 bits (RFC 4226 recommends at least 128). */
    public const int TOTP_SECRET_LENGTH = 32;

    /**
     * Issuer shown in the authenticator app. Fixed, not APP_NAME: `docker run` of the production image sets no APP_NAME,
     * which would make it Laravel's default "Laravel" (TE-030 AC2).
     */
    public const string TOTP_ISSUER = 'TravelEasy';

    public function __construct(private TwoFactorAuthenticationProvider $totp) {}

    /**
     * A database password: URL-safe base64 without padding.
     */
    public function databasePassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::PASSWORD_BYTES)), '+/', '-_'), '=');
    }

    /**
     * An APP_KEY for the configured cipher, in Laravel's `base64:` form.
     */
    public function appKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey(config()->string('app.cipher')));
    }

    /**
     * The super_admin password hash with the framework's hasher, wrapped as `base64:` so the `$` signs of the hash
     * survive env-file interpolation. The M1 provisioning decodes it before Hash::check().
     */
    public function passwordHash(#[\SensitiveParameter] string $password): string
    {
        return 'base64:'.base64_encode(Hash::make($password));
    }

    /**
     * A TOTP secret (base32) for the super_admin's authenticator app, made by the same provider Fortify verifies with.
     */
    public function totpSecret(): string
    {
        // The contract declares no parameter; Fortify's provider (and the Google2FA engine behind it) takes the length.
        // @phpstan-ignore arguments.count
        return $this->totp->generateSecretKey(self::TOTP_SECRET_LENGTH);
    }

    /**
     * The `otpauth://` URI for the QR code: issuer TravelEasy, and an account label that is a role name, never an e-mail address.
     */
    public function totpUri(#[\SensitiveParameter] string $secret): string
    {
        return $this->totp->qrCodeUrl(self::TOTP_ISSUER, 'super_admin', $secret);
    }
}
