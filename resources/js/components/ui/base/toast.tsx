import { XIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useSyncExternalStore } from 'react';
import type { KeyboardEvent } from 'react';
import { useToast, useToastRegion } from 'react-aria';
import { Button as AriaButton } from 'react-aria-components';
import { UNSTABLE_ToastQueue as ToastQueue } from 'react-aria-components';
import type { QueuedToast, ToastState } from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { SEVERITY_ICON, SEVERITY_TEXT } from './alert';
import type { Severity } from './alert';

export type ToastContent = {
    title: string;
    description?: string;
    severity: Severity;
};

export type ToastOptions = ToastContent & {
    /** Stay until dismissed. Always true for warnings and errors (WCAG 2.2.1). */
    persistent?: boolean;
};

/** At most three toasts are visible; the newest is on top. */
export const MAX_VISIBLE_TOASTS = 3;
/** Auto-dismiss never before 8 s, plus 1 s per 15 words (WCAG 2.2.1). */
export const BASE_TIMEOUT_MS = 8000;

export const toastQueue = new ToastQueue<ToastContent>({
    maxVisibleToasts: MAX_VISIBLE_TOASTS,
});

/** How long a confirmation toast stays: 8 s plus 1 s for every 15 words of text. */
export function toastTimeout(content: ToastContent): number {
    const words = `${content.title} ${content.description ?? ''}`
        .split(/\s+/)
        .filter(Boolean).length;

    return BASE_TIMEOUT_MS + Math.ceil(words / 15) * 1000;
}

/**
 * Shows a toast (TE-023, system.md 4.10). Success and info confirmations time out; warnings and
 * errors stay until dismissed. No sensitive data, and never the only place for something that must
 * not be missed (use an alert or banner). Render `<ToastRegion />` once per page.
 */
export function showToast({ persistent = false, ...content }: ToastOptions) {
    const critical =
        persistent ||
        content.severity === 'danger' ||
        content.severity === 'warning';

    return toastQueue.add(
        content,
        critical ? {} : { timeout: toastTimeout(content) },
    );
}

const EMPTY: QueuedToast<ToastContent>[] = [];

function ToastCard({
    toast,
    state,
}: {
    toast: QueuedToast<ToastContent>;
    state: ToastState<ToastContent>;
}) {
    const { t } = useTranslation();
    const ref = useRef<HTMLDivElement>(null);
    const { toastProps, titleProps, descriptionProps } = useToast(
        { toast },
        state,
        ref,
    );
    const { title, description, severity } = toast.content;
    const Icon = SEVERITY_ICON[severity];

    const dismiss = () => {
        // Keep focus inside the region when the focused toast goes away (the region hook restores
        // the previous focus only when the last one is gone).
        const current = ref.current;

        if (current !== null && current.contains(document.activeElement)) {
            const others = Array.from(
                current
                    .closest<HTMLElement>('[data-toast-region]')
                    ?.querySelectorAll<HTMLElement>('[data-toast]') ?? [],
            ).filter((element) => element !== current);
            others[0]?.focus();
        }
        state.close(toast.key);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Escape') {
            event.stopPropagation();
            dismiss();
        }
    };

    // The toast has no role of its own: the persistent live region around it announces it.
    // `useToast` would add role="alertdialog" and a role="alert" content wrapper; neither is used.
    const { tabIndex } = toastProps;

    return (
        // oxlint-disable-next-line jsx-a11y/no-static-element-interactions -- spec 4.10: no role of its own; Escape dismisses the focused toast
        <div
            ref={ref}
            data-toast=""
            tabIndex={tabIndex}
            onKeyDown={onKeyDown}
            className="pointer-events-auto flex items-start gap-3 rounded-xl border border-border bg-surface p-3 pl-4 text-text shadow-(--shadow)"
        >
            <Icon
                aria-hidden="true"
                className={cn(
                    'mt-0.5 size-5 shrink-0',
                    SEVERITY_TEXT[severity],
                )}
            />
            <div className="min-w-0 flex-1">
                <p {...titleProps} className="font-bold">
                    {title}
                </p>
                {description !== undefined && (
                    <p {...descriptionProps} className="text-sm">
                        {description}
                    </p>
                )}
            </div>
            <AriaButton
                onPress={dismiss}
                aria-label={t('toast.dismiss', { title })}
                className="-my-1 -mr-1 flex size-11 shrink-0 items-center justify-center rounded-md"
            >
                <span className="flex size-9 items-center justify-center rounded-md hover:bg-surface-2">
                    <XIcon aria-hidden="true" className="size-5" />
                </span>
            </AriaButton>
        </div>
    );
}

/**
 * The toast region (TE-023, system.md 4.10). Render once per page, next to `ValidationAnnouncer`.
 * It is present from the first render: a persistent `role="status"` (polite) container for
 * confirmations and a persistent `role="alert"` container for errors, so toasts inserted later are
 * announced. Toasts take no focus; they are reachable in the tab order and by F6 (the region is a
 * landmark), pause their timers on hover and focus, and Escape or the close button dismisses the
 * focused one. AC11: RAC's own `Toast` always renders `role="alertdialog"` (its props are merged
 * last), so the toasts are built on `useToast`/`useToastRegion` instead. Bottom-left from md up
 * (owner decision), full width on small screens; `scroll-padding-bottom` keeps focus uncovered.
 */
export function ToastRegion() {
    const { t } = useTranslation();
    const ref = useRef<HTMLDivElement>(null);
    const visible = useSyncExternalStore(
        (notify) => toastQueue.subscribe(notify),
        () => toastQueue.visibleToasts,
        () => EMPTY,
    );
    const state = useMemo<ToastState<ToastContent>>(
        () => ({
            visibleToasts: visible,
            add: (content, options) => toastQueue.add(content, options),
            close: (key) => {
                toastQueue.close(key);
            },
            pauseAll: () => {
                toastQueue.pauseAll();
            },
            resumeAll: () => {
                toastQueue.resumeAll();
            },
        }),
        [visible],
    );
    const { regionProps } = useToastRegion(
        { 'aria-label': t('toast.region') },
        state,
        ref,
    );
    const count = visible.length;

    // WCAG 2.4.11: a focused control must not hide behind the toasts.
    useEffect(() => {
        const element = ref.current;
        const root = document.documentElement;

        if (element === null || count === 0) {
            root.style.removeProperty('scroll-padding-bottom');

            return;
        }
        const update = () => {
            root.style.setProperty(
                'scroll-padding-bottom',
                `${String(element.getBoundingClientRect().height + 16)}px`,
            );
        };
        update();

        if (typeof ResizeObserver === 'undefined') {
            return () => {
                root.style.removeProperty('scroll-padding-bottom');
            };
        }
        const observer = new ResizeObserver(update);
        observer.observe(element);

        return () => {
            observer.disconnect();
            root.style.removeProperty('scroll-padding-bottom');
        };
    }, [count]);

    const status = visible.filter(
        (toast) => toast.content.severity !== 'danger',
    );
    const alerts = visible.filter(
        (toast) => toast.content.severity === 'danger',
    );

    return (
        <div
            {...regionProps}
            ref={ref}
            data-toast-region=""
            className="pointer-events-none fixed inset-x-0 bottom-0 z-60 flex flex-col gap-2 p-4 md:right-auto md:w-full md:max-w-[calc(26.25rem+2rem)]"
        >
            <output className="flex flex-col gap-2">
                {status.map((toast) => (
                    <ToastCard key={toast.key} toast={toast} state={state} />
                ))}
            </output>
            <div role="alert" className="flex flex-col gap-2">
                {alerts.map((toast) => (
                    <ToastCard key={toast.key} toast={toast} state={state} />
                ))}
            </div>
        </div>
    );
}
