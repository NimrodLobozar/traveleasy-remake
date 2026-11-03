import { router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';
import { isLocale, LOCALES, useTranslation } from '@/lib/i18n';
import type { Locale } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { update } from '@/routes/locale';

/**
 * Language control (TE-017): a form with one submit button per language, so it works without
 * JavaScript (a normal POST that redirects back, AC5). With JavaScript the submit becomes an
 * Inertia visit that keeps the component mounted, so focus stays on the pressed button (AC2).
 * The buttons carry `lang`; `hreflang` exists only on links and this control posts, so it is not
 * set. The visible text is the language's own name, which is also its accessible name (WCAG 2.5.3).
 */
export default function LanguageSwitch() {
    const { t, locale } = useTranslation();
    const { csrfToken } = usePage().props as { csrfToken?: unknown };
    const [changed, setChanged] = useState(false);
    const [failed, setFailed] = useState<string | null>(null);

    const names: Record<Locale, string> = {
        nl: t('shell.lang.nl'),
        en: t('shell.lang.en'),
    };

    const onSubmit = (event: SyntheticEvent<HTMLFormElement, SubmitEvent>) => {
        const target = event.nativeEvent.submitter;
        const next =
            target instanceof HTMLButtonElement ? target.value : undefined;
        if (!isLocale(next)) {
            return;
        }
        event.preventDefault();
        if (next === locale) {
            return;
        }
        setFailed(null);
        router.post(
            update().url,
            { locale: next },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setChanged(true);
                },
                onError: (errors) => {
                    // The server's message for `locale`, translated by key; else the generic text.
                    setFailed(errors.locale ?? t('common.error.generic'));
                },
            },
        );
    };

    return (
        <form method="post" action={update().url} onSubmit={onSubmit}>
            {typeof csrfToken === 'string' && (
                <input type="hidden" name="_token" value={csrfToken} />
            )}
            <fieldset className="m-0 inline-flex min-w-0 overflow-hidden rounded-md border-2 border-border p-0">
                <legend className="sr-only">{t('shell.lang.label')}</legend>
                {LOCALES.map((option) => {
                    const current = option === locale;

                    return (
                        <button
                            key={option}
                            type="submit"
                            name="locale"
                            value={option}
                            lang={option}
                            aria-current={current ? 'true' : undefined}
                            className={cn(
                                'inline-flex min-h-11 items-center gap-1.5 px-3 font-medium md:min-h-10',
                                current
                                    ? 'bg-brand font-bold text-on-brand'
                                    : 'bg-surface text-text hover:bg-surface-2',
                            )}
                        >
                            {current && (
                                <Check
                                    aria-hidden="true"
                                    focusable="false"
                                    className="size-4"
                                />
                            )}
                            {names[option]}
                        </button>
                    );
                })}
            </fieldset>
            {failed !== null && (
                <p
                    role="alert"
                    className="mt-1 text-sm font-medium text-danger"
                >
                    {failed}
                </p>
            )}
            {/* Rendered on every page so it exists before its text changes; the text is in the new language. */}
            <output
                aria-live="polite"
                aria-atomic="true"
                className="sr-only"
                data-testid="language-announcer"
            >
                {changed
                    ? t('shell.lang.changed', { language: names[locale] })
                    : ''}
            </output>
        </form>
    );
}
