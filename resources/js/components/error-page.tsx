import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { useTranslation } from '@/lib/i18n';
import { home } from '@/routes';

export const ERROR_STATUSES = [403, 404, 419, 429, 500, 503] as const;
export type ErrorStatus = (typeof ERROR_STATUSES)[number];

export function isErrorStatus(value: unknown): value is ErrorStatus {
    return ERROR_STATUSES.some((status) => status === value);
}

export type ErrorPageProps = {
    status: number;
    /** The same value as the X-Request-Id response header and the log line (TE-019 AC3). */
    requestId?: string | null;
    /** Seconds from the Retry-After header; shown for 429 only. */
    retryAfter?: number | null;
};

const linkPrimary =
    'inline-flex min-h-12 items-center justify-center rounded-md border-2 border-brand bg-brand px-5 font-bold text-on-brand hover:bg-brand-hover';
const linkSecondary =
    'inline-flex min-h-12 items-center justify-center rounded-md border-2 border-border bg-surface px-5 font-bold text-text hover:bg-surface-2';

/**
 * One page for 403, 404, 419, 429, 500 and 503 (TE-019), rendered inside the public shell. The
 * copy is chosen by status from literal i18n keys; an unknown status falls back to the 500 text.
 * It shows no message from the server, only the status, the request id and (429) the wait.
 * Focus moves to the <h1> on mount (AC7), as RouteFocus does for later visits.
 */
export default function ErrorPage({
    status,
    requestId,
    retryAfter,
}: ErrorPageProps) {
    const { t, formatNumber } = useTranslation();
    const heading = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        heading.current?.focus({ preventScroll: true });
    }, []);

    const copy: Record<ErrorStatus, { title: string; body: string }> = {
        403: { title: t('error.403.title'), body: t('error.403.body') },
        404: { title: t('error.404.title'), body: t('error.404.body') },
        419: { title: t('error.419.title'), body: t('error.419.body') },
        429: { title: t('error.429.title'), body: t('error.429.body') },
        500: { title: t('error.500.title'), body: t('error.500.body') },
        503: { title: t('error.503.title'), body: t('error.503.body') },
    };
    const shown: ErrorStatus = isErrorStatus(status) ? status : 500;
    const { title, body } = copy[shown];
    const wait =
        shown === 429 &&
        typeof retryAfter === 'number' &&
        Number.isFinite(retryAfter) &&
        retryAfter > 0
            ? formatNumber(Math.ceil(retryAfter), {
                  style: 'unit',
                  unit: 'second',
                  unitDisplay: 'long',
              })
            : null;

    return (
        <>
            <Head title={title} />
            <div className="mx-auto flex max-w-3xl flex-col items-start gap-4 px-4 py-12 md:px-8 md:py-20">
                <svg
                    aria-hidden="true"
                    focusable="false"
                    width="160"
                    height="64"
                    viewBox="0 0 160 64"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeDasharray="2 8"
                    className="text-brand"
                >
                    <path d="M6 52 C 40 4, 100 4, 138 40" />
                    <circle
                        cx="146"
                        cy="46"
                        r="6"
                        fill="currentColor"
                        stroke="none"
                    />
                </svg>
                <p className="font-mono text-sm font-bold tracking-wide text-text-muted">
                    {t('error.code', { code: String(shown) })}
                </p>
                <h1
                    ref={heading}
                    tabIndex={-1}
                    className="text-4xl font-extrabold text-balance"
                >
                    {title}
                </h1>
                <p className="max-w-[60ch] text-lg text-text-muted">{body}</p>
                {wait !== null && (
                    <p className="font-medium">
                        {t('error.retry_after', { wait })}
                    </p>
                )}
                {requestId ? (
                    <p className="font-mono text-sm break-all text-text-muted">
                        {t('error.reference', { id: requestId })}
                    </p>
                ) : null}
                <div className="mt-2 flex flex-wrap gap-3">
                    {shown === 419 ? (
                        <button
                            type="button"
                            onClick={() => {
                                window.location.reload();
                            }}
                            className={linkPrimary}
                        >
                            {t('error.reload')}
                        </button>
                    ) : null}
                    <Link
                        href={home()}
                        className={shown === 419 ? linkSecondary : linkPrimary}
                    >
                        {t('error.home')}
                    </Link>
                    {shown === 404 ? (
                        <a href="/search" className={linkSecondary}>
                            {t('error.search')}
                        </a>
                    ) : null}
                </div>
            </div>
        </>
    );
}
