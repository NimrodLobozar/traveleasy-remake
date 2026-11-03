import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link } from './link';

export type PaginationProps = {
    /** Current page, 1-based. */
    page: number;
    pageCount: number;
    /** URL of a page, e.g. `(n) => '/flights?page=' + n`. Pages are real links (SSR-friendly). */
    hrefFor: (page: number) => string;
    className?: string;
};

type Item = number | 'gap-start' | 'gap-end';

/** First, last, the current page and its neighbours; a gap of exactly one page is filled in. */
export function pageItems(page: number, pageCount: number): Item[] {
    const wanted = new Set(
        [1, page - 1, page, page + 1, pageCount].filter(
            (n) => n >= 1 && n <= pageCount,
        ),
    );
    const sorted = [...wanted].sort((a, b) => a - b);
    const items: Item[] = [];

    sorted.forEach((n, index) => {
        const previous = sorted[index - 1];

        if (previous !== undefined && n - previous === 2) {
            items.push(previous + 1);
        } else if (previous !== undefined && n - previous > 2) {
            items.push(index === 1 ? 'gap-start' : 'gap-end');
        }
        items.push(n);
    });

    return items;
}

const BOX =
    'inline-flex min-h-11 min-w-11 items-center justify-center gap-1 rounded-md border-2 px-3 font-bold no-underline tabular-nums';
const IDLE =
    'border-border bg-surface text-text hover:border-text hover:bg-surface-2 hover:text-text';
const CURRENT =
    'border-brand bg-brand text-on-brand underline decoration-2 underline-offset-4 hover:border-brand-hover hover:bg-brand-hover hover:text-on-brand forced-colors:border-[Highlight]';
const DISABLED =
    'cursor-not-allowed border-dashed border-line bg-surface-2 text-text-muted';

/** A previous/next control: a link, or a dimmed non-link at the first/last page. */
function Step({
    href,
    children,
}: {
    href: string | null;
    children: ReactNode;
}) {
    if (href === null) {
        return (
            <span
                // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role -- spec 4.12: a disabled link has no href, so no <a>
                role="link"
                aria-disabled="true"
                className={cn(BOX, DISABLED)}
            >
                {children}
            </span>
        );
    }

    return (
        <Link href={href} className={cn(BOX, IDLE)}>
            {children}
        </Link>
    );
}

/**
 * Pagination (TE-023, system.md 4.12): a `nav` with a list of real links. The current page has
 * `aria-current="page"`; first and last page show Previous/Next as dimmed non-links. Below md only
 * "Page 3 of 12" sits between the arrows. After navigating, move focus to the results heading
 * (`tabindex="-1"`, "Flights, page 3 of 12"); that focus move is the only announcement.
 */
export function Pagination({
    page,
    pageCount,
    hrefFor,
    className,
}: PaginationProps) {
    const { t } = useTranslation();
    const current = Math.min(Math.max(page, 1), Math.max(pageCount, 1));

    return (
        <nav aria-label={t('pagination.label')} className={className}>
            <ul className="flex flex-wrap items-center justify-center gap-2">
                <li>
                    <Step href={current > 1 ? hrefFor(current - 1) : null}>
                        <ChevronLeftIcon
                            aria-hidden="true"
                            className="size-4"
                        />
                        <span className="max-md:sr-only">
                            {t('pagination.prev')}
                        </span>
                    </Step>
                </li>
                <li className="md:hidden">
                    <span className="px-2 font-medium tabular-nums">
                        {t('pagination.of', {
                            page: String(current),
                            total: String(pageCount),
                        })}
                    </span>
                </li>
                {pageItems(current, pageCount).map((item) =>
                    typeof item === 'string' ? (
                        <li
                            key={item}
                            aria-hidden="true"
                            className="hidden px-1 md:block"
                        >
                            …
                        </li>
                    ) : (
                        <li key={item} className="hidden md:block">
                            <Link
                                href={hrefFor(item)}
                                aria-current={
                                    item === current ? 'page' : undefined
                                }
                                className={cn(
                                    BOX,
                                    item === current ? CURRENT : IDLE,
                                )}
                            >
                                <span className="sr-only">
                                    {t('pagination.page')}
                                </span>{' '}
                                {item}
                            </Link>
                        </li>
                    ),
                )}
                <li>
                    <Step
                        href={current < pageCount ? hrefFor(current + 1) : null}
                    >
                        <span className="max-md:sr-only">
                            {t('pagination.next')}
                        </span>
                        <ChevronRightIcon
                            aria-hidden="true"
                            className="size-4"
                        />
                    </Step>
                </li>
            </ul>
        </nav>
    );
}
