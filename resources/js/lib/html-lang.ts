import { router } from '@inertiajs/react';
import { isLocale } from '@/lib/i18n';

const apply = (locale: unknown): void => {
    if (isLocale(locale) && document.documentElement.lang !== locale) {
        document.documentElement.lang = locale;
    }
};

/**
 * Keeps `<html lang>` in step with the `locale` prop after client-side visits (the server only
 * sets it on a full page load), so a language switch without a reload still changes the page
 * language for assistive technology and spell checkers (TE-017 AC2). Browser only. `success`
 * covers visits that keep the same URL (the switch itself; Inertia sends no `navigate` for them),
 * `navigate` covers history back/forward.
 */
export function syncHtmlLang(): () => void {
    const offSuccess = router.on('success', (event) => {
        apply(event.detail.page.props.locale);
    });
    const offNavigate = router.on('navigate', (event) => {
        apply(event.detail.page.props.locale);
    });

    return () => {
        offSuccess();
        offNavigate();
    };
}
