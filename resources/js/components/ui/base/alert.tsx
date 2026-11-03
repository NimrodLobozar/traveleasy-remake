import {
    CircleAlertIcon,
    CircleCheckIcon,
    InfoIcon,
    TriangleAlertIcon,
    XIcon,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button as AriaButton } from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { nextFocusableAfter } from './focus';

export type Severity = 'info' | 'success' | 'warning' | 'danger';

export const SEVERITY_ICON = {
    info: InfoIcon,
    success: CircleCheckIcon,
    warning: TriangleAlertIcon,
    danger: CircleAlertIcon,
} as const;

export const SEVERITY_TEXT: Record<Severity, string> = {
    info: 'text-info',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
};

const BORDER: Record<Severity, string> = {
    info: 'border-info',
    success: 'border-success',
    warning: 'border-warning',
    danger: 'border-danger',
};

export type AlertProps = {
    severity?: Severity;
    title: string;
    children?: ReactNode;
    /** Only informational alerts can be dismissed (system.md 4.10). */
    dismissible?: boolean;
    onDismiss?: () => void;
    className?: string;
};

/**
 * Inline alert (TE-022, system.md 4.10). Info, success and warning use `role="status"`; danger uses
 * `role="alert"` (use sparingly, one per event). Dismissal moves focus to the next focusable element.
 */
export function Alert({
    severity = 'info',
    title,
    children,
    dismissible = false,
    onDismiss,
    className,
}: AlertProps) {
    const { t } = useTranslation();
    const ref = useRef<HTMLDivElement>(null);
    const [dismissed, setDismissed] = useState(false);
    const Icon = SEVERITY_ICON[severity];

    if (dismissed) {
        return null;
    }

    return (
        <div
            ref={ref}
            // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role -- <output> cannot hold block content; alert has no native tag
            role={severity === 'danger' ? 'alert' : 'status'}
            className={cn(
                'flex items-start gap-3 rounded-md border-2 bg-surface p-4 text-text',
                BORDER[severity],
                className,
            )}
        >
            <Icon
                aria-hidden="true"
                className={cn(
                    'mt-0.5 size-5 shrink-0',
                    SEVERITY_TEXT[severity],
                )}
            />
            <div className="min-w-0 flex-1">
                <p className="font-bold">{title}</p>
                {children !== undefined && (
                    <div className="mt-1">{children}</div>
                )}
            </div>
            {dismissible && severity === 'info' && (
                <AriaButton
                    aria-label={t('ui.close')}
                    onPress={() => {
                        const next = ref.current
                            ? nextFocusableAfter(ref.current)
                            : null;
                        setDismissed(true);
                        next?.focus();
                        onDismiss?.();
                    }}
                    className="-m-2 flex size-11 shrink-0 items-center justify-center rounded-md hover:bg-surface-2"
                >
                    <XIcon aria-hidden="true" className="size-5" />
                </AriaButton>
            )}
        </div>
    );
}
