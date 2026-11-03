<?php

declare(strict_types=1);

namespace App\Support\Security;

use InvalidArgumentException;

/**
 * The proxies whose X-Forwarded-For/-Proto/-Port headers are trusted (TE-012 AC12, ADR-0002 D6).
 *
 * Read from `security.trusted_proxies` (env TRUSTED_PROXIES): a comma-separated list of IP addresses or CIDR ranges,
 * in production exactly Nginx Proxy Manager's single fixed IP on `npm_shared` (TE-026); the production boot check
 * refuses any network range (a /32 or /128 is a single address). Ranges are accepted only outside production. Empty means
 * no proxy is trusted.
 * Anything that trusts every caller (`*`, `**`, `REMOTE_ADDR`, a /0 range) or is not an IP/CIDR is rejected loudly with
 * an InvalidArgumentException: the boot check refuses to start and the middleware refuses to serve.
 */
final readonly class TrustedProxies
{
    /** @param  list<string>  $addresses */
    private function __construct(public array $addresses) {}

    public static function fromConfig(): self
    {
        $value = config('security.trusted_proxies');

        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException('TRUSTED_PROXIES must be a comma-separated list of IP addresses or CIDR ranges.');
        }

        return self::parse($value ?? '');
    }

    /**
     * @throws InvalidArgumentException when an entry is not a specific IP address or CIDR range
     */
    public static function parse(string $value): self
    {
        $addresses = [];

        foreach (explode(',', $value) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (! self::isSpecificAddressOrRange($entry)) {
                throw new InvalidArgumentException(sprintf(
                    'TRUSTED_PROXIES contains [%s]: only IP addresses or CIDR ranges of the reverse proxy are allowed, '
                    .'never "*", "**", "REMOTE_ADDR" or a /0 range (see .env.example).',
                    mb_substr($entry, 0, 64),
                ));
            }

            $addresses[] = $entry;
        }

        return new self($addresses);
    }

    public function isEmpty(): bool
    {
        return $this->addresses === [];
    }

    private static function isSpecificAddressOrRange(string $entry): bool
    {
        $parts = explode('/', $entry);

        if (count($parts) > 2 || filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (count($parts) === 1) {
            return true;
        }

        $maxPrefix = filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 32 : 128;
        $prefix = $parts[1];

        return preg_match('/\A[0-9]{1,3}\z/', $prefix) === 1 && (int) $prefix >= 1 && (int) $prefix <= $maxPrefix;
    }
}
