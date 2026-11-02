import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Waits for Inertia's <Head> to commit the new document title before it is read. */
const TITLE_SETTLE_MS = 30;

/**
 * After an Inertia navigation (never the first page load, so the skip link stays the first Tab
 * stop):
 * - forward visit: focus moves to the page's <h1> (or <main>); that focus move is the only
 *   announcement, the live region stays empty (it would be read twice);
 * - back/forward (history): focus moves to <main>, the saved scroll position is restored (the
 *   page is in manual scroll restoration), and the new document title is announced once in a
 *   polite live region, because <main> has no name of its own.
 * The live region is rendered on every page (also on the server), so it exists before its text
 * changes. The text is only the page title, never user data (TE-016 AC3, AC4).
 */
export default function RouteFocus() {
    const [announcement, setAnnouncement] = useState('');

    useEffect(() => {
        let visiting = false;
        let popped = false;
        let timer: ReturnType<typeof setTimeout> | undefined;
        const positions = new Map<string, number>();
        let pageUrl = window.location.pathname + window.location.search;

        const onPopState = () => {
            // Still the page we are leaving: remember where it was scrolled to.
            positions.set(pageUrl, window.scrollY);
            popped = true;
        };
        window.addEventListener('popstate', onPopState);
        // 'before' fires ahead of Inertia's own scroll reset; by 'start' the page is already at 0.
        const offBefore = router.on('before', () => {
            positions.set(pageUrl, window.scrollY);
        });
        const offStart = router.on('start', () => {
            visiting = true;
        });
        const offNavigate = router.on('navigate', (event) => {
            pageUrl = event.detail.page.url;
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
                if (fromHistory) {
                    const saved = positions.get(pageUrl) ?? 0;
                    window.scrollTo(0, saved);
                    // Inertia may reset the scroll after the page commit; retry once if so.
                    setTimeout(() => {
                        if (window.scrollY === 0 && saved > 0) {
                            window.scrollTo(0, saved);
                        }
                    }, 150);
                    setAnnouncement('');
                    setTimeout(() => {
                        setAnnouncement(document.title);
                    }, 50);
                } else {
                    setAnnouncement('');
                }
            }, TITLE_SETTLE_MS);
        });

        return () => {
            window.removeEventListener('popstate', onPopState);
            offBefore();
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
