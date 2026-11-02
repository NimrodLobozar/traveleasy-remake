import { DEFAULT_LOCALE } from './locales';
import type { Locale } from './locales';

/** Flat key -> string map for one locale (`lang/<locale>.json`, sent by the server). */
export type Messages = Readonly<Record<string, string>>;

export type Replacements = Readonly<Record<string, string | number>>;

export type Translator = (key: string, replacements?: Replacements) => string;

export type MissingKeyHandler = (key: string, locale: Locale) => void;

/** Test runs fail on a missing key; the app never throws (TE-009 AC6). */
export const throwOnMissingKey: MissingKeyHandler = (key, locale) => {
    throw new Error(`Missing translation key "${key}" for locale "${locale}"`);
};

/**
 * Replace Laravel-style :name placeholders (the same lang files serve PHP __(), so the syntax is
 * the same, including the :Name and :NAME casing variants). The result is a plain string; React
 * escapes it when rendered, so a value such as an img tag shows as text (AC9). Nothing here
 * produces HTML, and t() is never to be fed to dangerouslySetInnerHTML. Unknown placeholders
 * stay as written; a replaced value is never scanned for placeholders again.
 */
export function interpolate(
    message: string,
    replacements: Replacements | undefined,
): string {
    if (!replacements) {
        return message;
    }

    return message.replace(
        /:([A-Za-z_][A-Za-z0-9_]*)/g,
        (match, name: string) => {
            const key = Object.hasOwn(replacements, name)
                ? name
                : Object.keys(replacements).find(
                      (candidate) =>
                          candidate.toLowerCase() === name.toLowerCase(),
                  );
            const value = key === undefined ? undefined : replacements[key];

            if (key === undefined || value === undefined) {
                return match;
            }

            const text = String(value);

            if (name === key) {
                return text;
            }
            if (name === key.toUpperCase()) {
                return text.toUpperCase();
            }
            if (name === key.charAt(0).toUpperCase() + key.slice(1)) {
                return text.charAt(0).toUpperCase() + text.slice(1);
            }

            return match;
        },
    );
}

/**
 * Build `t()` for one locale. A pure function of its arguments, so the server render and the
 * browser hydration, which get the same page props, produce the same strings.
 */
export function createTranslator(
    messages: Messages,
    locale: Locale = DEFAULT_LOCALE,
    onMissing?: MissingKeyHandler,
): Translator {
    return (key, replacements) => {
        const message = Object.hasOwn(messages, key)
            ? messages[key]
            : undefined;

        if (message === undefined) {
            onMissing?.(key, locale);

            return key;
        }

        return interpolate(message, replacements);
    };
}
