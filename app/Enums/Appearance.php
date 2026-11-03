<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

/**
 * The theme choice (TE-018): the allow-list for the `appearance` cookie.
 *
 * `system` follows `prefers-color-scheme` in the browser; `light` and `dark` are explicit overrides the server renders
 * directly into `<html>`. Exported to TypeScript as `Appearance` (`npm run generate`).
 */
enum Appearance: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    /** Name of the first-party cookie, written by the browser (use-appearance). Not encrypted (bootstrap/app.php). */
    public const string COOKIE = 'appearance';

    /**
     * The choice in the request's cookie; anything not on the allow-list (another value, an array, a huge string) is
     * `system`. The raw value is never returned, echoed or logged.
     */
    public static function fromRequest(Request $request): self
    {
        // Read the raw bag: a crafted `appearance[]=…` cookie is an array, which InputBag::get() would reject with a 400.
        $value = $request->cookies->all()[self::COOKIE] ?? null;

        return is_string($value) ? self::tryFrom($value) ?? self::System : self::System;
    }

    /**
     * The `content` of `<meta name="color-scheme">`: the explicit scheme, or both when the browser decides.
     */
    public function colorScheme(): string
    {
        return match ($this) {
            self::System => 'light dark',
            self::Light => 'light',
            self::Dark => 'dark',
        };
    }
}
