import { useSyncExternalStore } from 'react';

const MOBILE_BREAKPOINT = 768;
const MOBILE_QUERY = `(max-width: ${String(MOBILE_BREAKPOINT - 1)}px)`;

// Created on first use in the browser, never while rendering on the server (TE-007).
let mql: MediaQueryList | undefined;

function getMql(): MediaQueryList {
    mql ??= window.matchMedia(MOBILE_QUERY);

    return mql;
}

function mediaQueryListener(callback: (event: MediaQueryListEvent) => void) {
    const list = getMql();

    list.addEventListener('change', callback);

    return () => {
        list.removeEventListener('change', callback);
    };
}

function isSmallerThanBreakpoint(): boolean {
    return getMql().matches;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useIsMobile(): boolean {
    return useSyncExternalStore(
        mediaQueryListener,
        isSmallerThanBreakpoint,
        getServerSnapshot,
    );
}
