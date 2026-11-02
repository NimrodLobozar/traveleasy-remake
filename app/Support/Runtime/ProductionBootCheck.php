<?php

declare(strict_types=1);

namespace App\Support\Runtime;

use App\Support\Security\TrustedProxies;
use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;

/**
 * The start-up safety check of the production image (TE-012 AC8, docs/plan.md § 7), run by the entrypoint's `app`,
 * `worker` and `scheduler` roles before anything else (`php artisan traveleasy:boot-check`). Never by the `ssr` role
 * or the pass-through for one-off commands (ADR-0002 D3), so the secrets helper of TE-025 can run without a key.
 *
 * In production: APP_DEBUG must be off and APP_KEY must be a valid key for the configured cipher.
 * In every environment: TRUSTED_PROXIES must be specific addresses (never `*`). In production it must be single
 * IPs (NPM's fixed IP on npm_shared), not network ranges.
 * Messages name the variable, never its value.
 */
final readonly class ProductionBootCheck
{
    public function __construct(
        private bool $production,
        private bool $debug,
        private string $key,
        private string $cipher,
        private string $trustedProxies,
    ) {}

    public static function fromConfig(): self
    {
        $key = config('app.key');
        $cipher = config('app.cipher');
        $proxies = config('security.trusted_proxies');

        return new self(
            production: app()->isProduction(),
            debug: config()->boolean('app.debug'),
            key: is_string($key) ? $key : '',
            cipher: is_string($cipher) ? $cipher : '',
            trustedProxies: is_string($proxies) ? $proxies : '',
        );
    }

    /**
     * @return list<string> one message per problem; empty = safe to start
     */
    public function violations(): array
    {
        $violations = [];

        if ($this->production && $this->debug) {
            $violations[] = 'APP_DEBUG is true with APP_ENV=production. Debug mode shows stack traces, SQL and '
                .'configuration to visitors; set APP_DEBUG=false.';
        }

        if ($this->production && ! $this->hasValidKey()) {
            $violations[] = 'APP_KEY is empty or not a valid key for the configured cipher. Generate one with the '
                .'secrets helper (docs/deploy/production-image.md) and set APP_KEY.';
        }

        try {
            $proxies = TrustedProxies::parse($this->trustedProxies);

            if ($this->production) {
                foreach ($proxies->addresses as $address) {
                    if (preg_match('#/(\d+)\z#', $address, $match) === 1 && (int) $match[1] < (str_contains($address, ':') ? 128 : 32)) {
                        $violations[] = 'TRUSTED_PROXIES contains a network range. In production set the single fixed IP of '
                            .'Nginx Proxy Manager on npm_shared (a plain IP or /32), not the whole network (ADR-0002 D6).';
                        break;
                    }
                }
            }
        } catch (InvalidArgumentException $e) {
            $violations[] = $e->getMessage();
        }

        return $violations;
    }

    private function hasValidKey(): bool
    {
        if ($this->key === '') {
            return false;
        }

        $key = str_starts_with($this->key, 'base64:')
            ? base64_decode(substr($this->key, 7), true)
            : $this->key;

        return is_string($key) && Encrypter::supported($key, strtolower($this->cipher));
    }
}
