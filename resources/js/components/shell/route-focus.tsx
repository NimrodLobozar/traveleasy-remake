import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Waits for Inertia's <Head> to commit the new document title before it is read. */
const TITLE_SETTLE_MS = 30;

/**
 * After an Inertia navigation: moves focus to the page's <h1> (or <main>; always <main> after
 * back/forward), and announces the new document title once in a polite live region. It never
 * runs for the first page load, so the skip link stays the first Tab stop. The live region is
 * rendered on every page (also on the server), so it exists before its text changes. The text is
 * only the page title, never user data (TE-016 AC3, AC4).
 */
export default function RouteFocus() {
    const [announcement, setAnnouncement] = useState('');

    useEffect(() => {
        let visiting = false;
        let popped = false;
        let timer: ReturnType<typeof setTimeout> | undefined;

        const onPopState = () => {
            popped = true;
        };
        window.addEventListener('popstate', onPopState);
        const offStart = router.on('start', () => {
            visiting = true;
        });
        const offNavigate = router.on('navigate', () => {
            const fromHistory = popped;
            popped = false;
            if (!visiting && !fromHistory) {
                return;
            }
            visiting = false;
            clearTimeout(timer);
            timer = setTimeout(() => {
                const main = document.getElementById('main');
                const heading = fromHistory
                    ? null
                    : (main ?? document).querySelector('h1');
                const target = heading ?? main;
                if (target) {
                    if (!target.hasAttribute('tabindex')) {
                        target.setAttribute('tabindex', '-1');
                    }
                    target.focus({ preventScroll: true });
                }
                setAnnouncement('');
                setTimeout(() => {
                    setAnnouncement(document.title);
                }, 50);
            }, TITLE_SETTLE_MS);
        });

        return () => {
            window.removeEventListener('popstate', onPopState);
            offStart();
            offNavigate();
            clearTimeout(timer);
        };
    }, []);

    return (
        <output
            aria-live="polite"
            aria-atomic="true"
            className="sr-only"
            data-testid="route-announcer"
        >
            {announcement}
        </output>
    );
}
