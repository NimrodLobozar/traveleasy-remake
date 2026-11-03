import { useEffect, useState } from 'react';
import type { RefObject } from 'react';

/**
 * True while `ref`'s element scrolls (content wider or taller than the box). Used to make scroll
 * regions keyboard-focusable only when they overflow (WCAG 2.1.1, axe scrollable-region-focusable).
 * False on the server and on first render, so hydration matches.
 */
export function useIsOverflowing(ref: RefObject<HTMLElement | null>): boolean {
    const [overflowing, setOverflowing] = useState(false);

    useEffect(() => {
        const element = ref.current;

        if (element === null) {
            return;
        }

        const check = () => {
            setOverflowing(
                element.scrollWidth > element.clientWidth + 1 ||
                    element.scrollHeight > element.clientHeight + 1,
            );
        };
        check();

        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(check);
        observer.observe(element);
        Array.from(element.children).forEach((child) => {
            observer.observe(child);
        });

        return () => {
            observer.disconnect();
        };
    }, [ref]);

    return overflowing;
}
