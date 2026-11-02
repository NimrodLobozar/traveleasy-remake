<?php

declare(strict_types=1);

namespace App\Support\I18n;

use App\Enums\Locale;
use Illuminate\Translation\Translator;

/**
 * The UI strings sent to the frontend (TE-009): the flat key => string map of `lang/<locale>.json`, merged over
 * the fallback locale's file, so a key missing in the active locale carries its fallback text.
 *
 * Only the active locale's map is sent. Values are plain text with Laravel `:name` placeholders, filled in by
 * the frontend's t() (which escapes them, AC9).
 */
final readonly class Translations
{
    public function __construct(private Translator $translator) {}

    /**
     * @return array<string, string>
     */
    public function for(Locale $locale): array
    {
        $fallback = Locale::fallback();

        $lines = $locale === $fallback
            ? $this->lines($locale)
            : [...$this->lines($fallback), ...$this->lines($locale)];

        ksort($lines, SORT_STRING);

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function lines(Locale $locale): array
    {
        $lines = [];

        // JSON translation files are the `*` group in the `*` namespace.
        foreach ($this->translator->getLoader()->load($locale->value, '*', '*') as $key => $value) {
            if (is_string($value)) {
                $lines[(string) $key] = $value;
            }
        }

        return $lines;
    }
}
