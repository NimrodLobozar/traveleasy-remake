import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const COOKIE = 'appearance';
const APPEARANCES: readonly Appearance[] = ['system', 'light', 'dark'];

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';
let read = false;

export const isAppearance = (value: unknown): value is Appearance =>
    typeof value === 'string' &&
    (APPEARANCES as readonly string[]).includes(value);

const prefersDark = (): boolean => {
    if (
        typeof window === 'undefined' ||
        typeof window.matchMedia !== 'function'
    ) {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

/**
 * The cookie is the single source of truth (TE-018): the server reads it to render the right
 * class before first paint, so the client must not keep a second copy (no localStorage). First
 * party, SameSite=Lax, one year, no personal data; the server re-validates it against the same
 * allow-list.
 */
const setCookie = (value: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = 365 * 24 * 60 * 60;
    const secure = window.location.protocol === 'https:' ? ';Secure' : '';
    document.cookie = `${COOKIE}=${value};path=/;max-age=${String(maxAge)};SameSite=Lax${secure}`;
};

/** An unknown or missing cookie value is `system`, never used as-is. */
export const readStoredAppearance = (): Appearance => {
    if (typeof document === 'undefined') {
        return 'system';
    }
    const match = document.cookie
        .split('; ')
        .find((part) => part.startsWith(`${COOKIE}=`));
    const value = match?.slice(COOKIE.length + 1);

    return isAppearance(value) ? value : 'system';
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    // Set through the CSSOM, which the strict CSP (no inline styles) allows.
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => {
    listeners.forEach((listener) => {
        listener();
    });
};

const mediaQuery = (): MediaQueryList | null => {
    if (
        typeof window === 'undefined' ||
        typeof window.matchMedia !== 'function'
    ) {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

/** While on `system` the theme follows the device without a reload (TE-018 AC6). */
const handleSystemThemeChange = (): void => {
    if (currentAppearance === 'system') {
        applyTheme('system');
        notify();
    }
};

let watched: MediaQueryList | null = null;

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    read = true;
    currentAppearance = readStoredAppearance();
    applyTheme(currentAppearance);
    notify();

    watched?.removeEventListener('change', handleSystemThemeChange);
    watched = mediaQuery();
    watched?.addEventListener('change', handleSystemThemeChange);
}

/** The client's snapshot: the cookie is read on first use, so it is right even before initializeTheme runs. */
const getSnapshot = (): Appearance => {
    if (!read && typeof document !== 'undefined') {
        read = true;
        currentAppearance = readStoredAppearance();
    }

    return currentAppearance;
};

/**
 * `serverAppearance` is what the server rendered with (the shared `appearance` prop). React uses it
 * for the server render and for hydration, so both agree; right after, the client snapshot (the
 * cookie) takes over.
 */
export function useAppearance(serverAppearance?: unknown): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        getSnapshot,
        () => (isAppearance(serverAppearance) ? serverAppearance : 'system'),
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;
        setCookie(mode);
        applyTheme(mode);
        notify();
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
