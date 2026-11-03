import { XIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button as AriaButton } from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { SEVERITY_ICON, SEVERITY_TEXT } from './alert';
import type { Severity } from './alert';
import { nextFocusableAfter } from './focus';

const BORDER: Record<Severity, string> = {
    info: 'border-b-info',
    success: 'border-b-success',
    warning: 'border-b-warning',
    danger: 'border-b-danger',
};

export type BannerProps = {
    /** Accessible name of the section landmark-free region, e.g. "Onderhoudsmelding". */
    label: string;
    severity?: Severity;
    children: ReactNode;
    /** An action such as "Ingelogd blijven". */
    action?: ReactNode;
    /** Only informational banners can be dismissed (system.md 4.11). */
    dismissible?: boolean;
    /** When set, dismissal is remembered for the session under this key. */
    storageKey?: string;
    onDismiss?: () => void;
    className?: string;
};

/**
 * Page-level banner (TE-022, system.md 4.11): a `section` with `aria-label` and no live region of
 * its own. For banners that must be announced, render them inside `BannerLiveRegion`.
 */
export function Banner({
    label,
    severity = 'info',
    children,
    action,
    dismissible = false,
    storageKey,
    onDismiss,
    className,
}: BannerProps) {
    const { t } = useTranslation();
    const ref = useRef<HTMLElement>(null);
    const [dismissed, setDismissed] = useState(false);
    const canDismiss = dismissible && severity === 'info';
    const Icon = SEVERITY_ICON[severity];

    useEffect(() => {
        if (
            canDismiss &&
            storageKey !== undefined &&
            window.sessionStorage.getItem(storageKey) === '1'
        ) {
            // oxlint-disable-next-line react/set-state-in-effect -- sessionStorage is only readable after hydration (SSR-safe)
            setDismissed(true);
        }
    }, [canDismiss, storageKey]);

    if (dismissed) {
        return null;
    }

    return (
        <section
            ref={ref}
            aria-label={label}
            className={cn(
                'flex flex-wrap items-center gap-x-4 gap-y-2 border-b-[3px] bg-surface-2 px-4 py-3 text-text',
                BORDER[severity],
                className,
            )}
        >
            <Icon
                aria-hidden="true"
                className={cn('size-5 shrink-0', SEVERITY_TEXT[severity])}
            />
            <div className="min-w-0 flex-1 basis-60">{children}</div>
            {action !== undefined && <div className="shrink-0">{action}</div>}
            {canDismiss && (
                <AriaButton
                    aria-label={t('ui.close')}
                    onPress={() => {
                        const next = ref.current
                            ? nextFocusableAfter(ref.current)
                            : null;
                        if (storageKey !== undefined) {
                            window.sessionStorage.setItem(storageKey, '1');
                        }
                        setDismissed(true);
                        next?.focus();
                        onDismiss?.();
                    }}
                    className="flex size-11 shrink-0 items-center justify-center rounded-md hover:bg-surface"
                >
                    <XIcon aria-hidden="true" className="size-5" />
                </AriaButton>
            )}
        </section>
    );
}

/**
 * The persistent, empty `role="alert"` container for the banner slot: it exists on page load, so a
 * banner injected into it later (session expiry) is announced reliably (WCAG 4.1.3).
 */
export function BannerLiveRegion({ children }: { children?: ReactNode }) {
    return (
        // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role -- must be a pre-existing alert container (spec 4.11)
        <div role="alert" aria-atomic="true">
            {children}
        </div>
    );
}
