import { Link as InertiaLink } from '@inertiajs/react';
import { ExternalLinkIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Link as AriaLink } from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type LinkProps = {
    href: string;
    children: ReactNode;
    className?: string;
    /** Set on the navigation link of the current page. */
    'aria-current'?: 'page';
};

export const LINK_CLASS =
    'rounded-sm text-brand underline decoration-1 underline-offset-[3px] hover:text-brand-hover hover:decoration-[3px]';

/** Absolute (`https://`, `//`) and `mailto:` style targets leave the app; everything else is internal. */
export function isExternalHref(href: string): boolean {
    return /^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(href);
}

/**
 * Link (TE-022, system.md 4.2). Always underlined. Internal targets use Inertia navigation; external
 * ones open in a new tab with an icon and a visually hidden hint. Link text must describe the target.
 */
export function Link({ href, children, className, ...props }: LinkProps) {
    const { t } = useTranslation();

    if (!isExternalHref(href)) {
        return (
            <InertiaLink
                href={href}
                className={cn(LINK_CLASS, className)}
                {...props}
            >
                {children}
            </InertiaLink>
        );
    }

    return (
        <AriaLink
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className={cn(
                LINK_CLASS,
                'inline-flex items-baseline gap-1',
                className,
            )}
            {...props}
        >
            {children}
            <ExternalLinkIcon
                aria-hidden="true"
                className="size-3.5 self-center"
            />{' '}
            <span className="sr-only">{t('ui.external')}</span>
        </AriaLink>
    );
}
