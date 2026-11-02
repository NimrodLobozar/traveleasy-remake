import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Brand from '@/components/shell/brand';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { login } from '@/routes';

// The destinations are built in later epics; until then they are plain links.
const NAV = [
    { key: 'shell.nav.search', href: '/search' },
    { key: 'shell.nav.offers', href: '/offers' },
    { key: 'shell.nav.bookings', href: '/bookings' },
    { key: 'shell.nav.help', href: '/help' },
] as const;

export default function SiteHeader() {
    const { t } = useTranslation();
    const { currentUrl } = useCurrentUrl();
    const [open, setOpen] = useState(false);
    const button = useRef<HTMLButtonElement>(null);

    // Escape closes the open menu and returns focus to its button; focus is not trapped.
    useEffect(() => {
        if (!open) {
            return;
        }
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                button.current?.focus();
            }
        };
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    const close = () => {
        setOpen(false);
    };

    return (
        <header className="border-b border-line bg-surface">
            <div className="mx-auto flex min-h-18 max-w-7xl flex-wrap items-center justify-between gap-x-6 px-4 py-2 md:px-8">
                <Brand />
                <button
                    ref={button}
                    type="button"
                    aria-expanded={open}
                    aria-controls="mobile-menu"
                    onClick={() => {
                        setOpen(!open);
                    }}
                    className="inline-flex min-h-11 items-center gap-2 rounded-md border-2 border-border px-4 font-bold text-text md:hidden"
                >
                    <svg
                        aria-hidden="true"
                        focusable="false"
                        width="20"
                        height="20"
                        viewBox="0 0 20 20"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                    >
                        <path d="M3 5h14M3 10h14M3 15h14" />
                    </svg>
                    {t('shell.menu')}
                </button>
                <div
                    id="mobile-menu"
                    className={cn(
                        'w-full flex-col gap-2 pb-2 md:flex md:w-auto md:flex-1 md:flex-row md:items-center md:justify-between md:pb-0',
                        open ? 'flex' : 'hidden',
                    )}
                >
                    <nav aria-label={t('shell.nav.label')} className="md:ml-6">
                        <ul className="flex flex-col md:flex-row md:gap-1">
                            {NAV.map((item) => {
                                const current = currentUrl === item.href;

                                return (
                                    <li key={item.href}>
                                        <a
                                            href={item.href}
                                            onClick={close}
                                            aria-current={
                                                current ? 'page' : undefined
                                            }
                                            className={cn(
                                                'flex min-h-11 items-center border-l-4 border-transparent px-3 font-medium text-text hover:text-brand md:border-b-[3px] md:border-l-0',
                                                current &&
                                                    'border-brand bg-surface-2 md:bg-transparent',
                                            )}
                                        >
                                            {t(item.key)}
                                        </a>
                                    </li>
                                );
                            })}
                        </ul>
                    </nav>
                    <div className="flex flex-col gap-2 md:flex-row md:items-center">
                        {/* Slot: language switch (TE-017) */}
                        {/* Slot: theme switch (TE-018) */}
                        <Link
                            href={login()}
                            onClick={close}
                            className="inline-flex min-h-11 items-center justify-center rounded-md border-2 border-brand bg-brand px-5 font-bold text-on-brand hover:bg-brand-hover"
                        >
                            {t('shell.login')}
                        </Link>
                    </div>
                </div>
            </div>
        </header>
    );
}
