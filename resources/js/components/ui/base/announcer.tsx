import { useEffect, useRef, useSyncExternalStore } from 'react';

/**
 * Persistent, visually hidden polite live region for blur-validation errors (system.md 4.3, WCAG
 * 4.1.3). Render `<ValidationAnnouncer />` once per page, present on load; fields call
 * `announce()` through `useBlurAnnounce`. On submit only the error summary announces (focus move).
 */
let message = '';
const listeners = new Set<() => void>();

function emit(): void {
    listeners.forEach((listener) => {
        listener();
    });
}

let pending: ReturnType<typeof setTimeout> | undefined;

/**
 * Sets the live-region text. An identical repeat is cleared first and set again in a timeout, so the
 * DOM changes and screen readers announce it again. Never touches `window` (SSR-safe).
 */
export function announce(text: string): void {
    clearTimeout(pending);
    if (text !== '' && text === message) {
        message = '';
        emit();
        pending = setTimeout(() => {
            message = text;
            emit();
        }, 50);

        return;
    }
    message = text;
    emit();
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function ValidationAnnouncer() {
    const text = useSyncExternalStore(
        subscribe,
        () => message,
        () => '',
    );

    return (
        <output
            data-validation-announcer
            aria-atomic="true"
            className="sr-only"
        >
            {text}
        </output>
    );
}

/**
 * Returns `onBlur`/`onFocus` handlers. When `error` appears shortly after a blur it is announced as
 * "Label: error"; errors that appear on submit (no preceding blur) are left to the summary.
 */
export function useBlurAnnounce(label: string, error: string | undefined) {
    const blurred = useRef(false);
    const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    useEffect(() => {
        if (blurred.current && error !== undefined && error !== '') {
            announce(`${label}: ${error}`);
            blurred.current = false;
        }
    }, [error, label]);

    useEffect(
        () => () => {
            clearTimeout(timer.current);
        },
        [],
    );

    return {
        onBlur: () => {
            blurred.current = true;
            clearTimeout(timer.current);
            timer.current = setTimeout(() => {
                blurred.current = false;
            }, 300);
        },
        onFocus: () => {
            blurred.current = false;
        },
    };
}
