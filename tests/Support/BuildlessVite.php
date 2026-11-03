<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/**
 * The Vite double every PHP test runs with (Tests\TestCase::withoutVite()): it behaves like a production build that
 * emits no tags, and it never reads public/build/manifest.json or public/hot. Laravel's own withoutVite() double still
 * reads public/hot (present while the dev server runs, absent in CI), so the suite behaved differently per machine;
 * and a partial double that only stubs __invoke() lets helpers such as Vite::asset() fall through to the manifest.
 *
 * Tests that need a real Vite call $this->withVite() and set it up explicitly (a temporary hot file).
 */
final class BuildlessVite extends Vite
{
    /**
     * @param  string|string[]  $entrypoints
     * @param  string|null  $buildDirectory
     */
    public function __invoke($entrypoints, $buildDirectory = null): HtmlString
    {
        return new HtmlString('');
    }

    /**
     * @param  string  $method
     * @param  array<array-key, mixed>  $parameters
     */
    public function __call($method, $parameters): string
    {
        return '';
    }

    public function __toString(): string
    {
        return '';
    }

    public function isRunningHot(): bool
    {
        return false;
    }

    /** @param  string|false  $key */
    public function useIntegrityKey($key): static
    {
        return $this;
    }

    /** @param  string  $path */
    public function useBuildDirectory($path): static
    {
        return $this;
    }

    /** @param  string  $path */
    public function useHotFile($path): static
    {
        return $this;
    }

    /** @param  array<int, string>  $entryPoints */
    public function withEntryPoints($entryPoints): static
    {
        return $this;
    }

    /** @param  mixed  $attributes */
    public function useScriptTagAttributes($attributes): static
    {
        return $this;
    }

    /** @param  mixed  $attributes */
    public function useStyleTagAttributes($attributes): static
    {
        return $this;
    }

    /** @param  mixed  $attributes */
    public function usePreloadTagAttributes($attributes): static
    {
        return $this;
    }

    /** @return array<string, mixed> */
    public function preloadedAssets(): array
    {
        return [];
    }

    public function reactRefresh(): ?HtmlString
    {
        return null;
    }

    /**
     * @param  string  $asset
     * @param  string|null  $buildDirectory
     */
    public function content($asset, $buildDirectory = null): string
    {
        return '';
    }

    /**
     * @param  string  $asset
     * @param  string|null  $buildDirectory
     */
    public function asset($asset, $buildDirectory = null): string
    {
        return '';
    }

    /** @param  string|null  $buildDirectory */
    public function manifestHash($buildDirectory = null): ?string
    {
        return null;
    }

    public function devServerUrl(): string
    {
        return '';
    }

    /** @param  string|string[]|null  $aliases */
    public function fonts($aliases = null): HtmlString
    {
        return new HtmlString('');
    }

    public function toHtml(): string
    {
        return '';
    }
}
