import { Button as AriaButton } from 'react-aria-components';
import type { ButtonProps as AriaButtonProps } from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { PendingSpinner } from './spinner';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost';
export type ButtonSize = 'sm' | 'md' | 'lg';

export type ButtonProps = Omit<AriaButtonProps, 'className' | 'children'> & {
    variant?: ButtonVariant;
    size?: ButtonSize;
    className?: string;
    children?: React.ReactNode;
};

const VARIANTS: Record<ButtonVariant, string> = {
    primary:
        'border-brand bg-brand text-on-brand hover:border-brand-hover hover:bg-brand-hover active:border-brand-active active:bg-brand-active',
    secondary:
        'border-border bg-surface text-text hover:border-text hover:bg-surface-2 active:border-brand',
    danger: 'border-danger bg-danger text-on-danger hover:border-danger-hover hover:bg-danger-hover active:border-danger-active active:bg-danger-active',
    ghost: 'border-transparent bg-transparent text-brand hover:bg-surface-2 active:underline',
};

/* Heights are min-heights (text spacing, 400 % zoom); coarse pointers always get 44 px. */
const SIZES: Record<ButtonSize, string> = {
    sm: 'min-h-9 px-3 pointer-coarse:min-h-11',
    md: 'min-h-11 px-4',
    lg: 'min-h-13 px-6',
};

/**
 * Button (TE-022, system.md 4.1). `type` defaults to "button". While `isPending` the width is kept
 * (label and pending text share one grid cell), the pending text is announced once through a status
 * region that exists from the first render, and RAC ignores presses.
 */
export function Button({
    variant = 'primary',
    size = 'md',
    className,
    children,
    isPending = false,
    type = 'button',
    ...props
}: ButtonProps) {
    const { t } = useTranslation();

    return (
        <>
            <AriaButton
                {...props}
                type={type}
                isPending={isPending}
                className={cn(
                    'inline-grid items-center justify-items-center rounded-md border-2 text-center font-bold transition-colors duration-(--motion-fast)',
                    'data-[disabled]:cursor-not-allowed data-[disabled]:border-dashed data-[disabled]:border-line data-[disabled]:bg-surface-2 data-[disabled]:text-text-muted',
                    'data-[pending]:cursor-progress',
                    VARIANTS[variant],
                    SIZES[size],
                    className,
                )}
            >
                <span
                    className={cn(
                        'col-start-1 row-start-1 inline-flex items-center justify-center gap-2',
                        isPending && 'invisible',
                    )}
                >
                    {children}
                </span>
                {isPending && (
                    <span className="col-start-1 row-start-1 inline-flex items-center justify-center gap-2">
                        <PendingSpinner />
                        {t('ui.pending')}
                    </span>
                )}
            </AriaButton>
            <output className="sr-only">
                {isPending ? t('ui.pending') : ''}
            </output>
        </>
    );
}
