import { useTranslation } from '@/lib/i18n';

const linkClass =
    'inline-flex min-h-11 items-center text-brand underline underline-offset-4 hover:text-brand-hover';

// The destinations are built in later epics; until then they are plain links.
export default function SiteFooter() {
    const { t } = useTranslation();

    return (
        <footer className="mt-auto border-t border-line bg-surface">
            <div className="mx-auto grid max-w-7xl gap-6 px-4 py-6 md:grid-cols-2 md:px-8">
                <nav aria-label={t('shell.footer.service.label')}>
                    <ul>
                        <li>
                            <a href="/help" className={linkClass}>
                                {t('shell.footer.service.help')}
                            </a>
                        </li>
                        <li>
                            <a href="/bookings" className={linkClass}>
                                {t('shell.footer.service.cancel')}
                            </a>
                        </li>
                    </ul>
                </nav>
                <nav aria-label={t('shell.footer.legal.label')}>
                    <ul>
                        <li>
                            <a href="/privacy" className={linkClass}>
                                {t('shell.footer.legal.privacy')}
                            </a>
                        </li>
                        <li>
                            <a href="/accessibility" className={linkClass}>
                                {t('shell.footer.legal.accessibility')}
                            </a>
                        </li>
                        <li>
                            <a href="/terms" className={linkClass}>
                                {t('shell.footer.legal.terms')}
                            </a>
                        </li>
                    </ul>
                </nav>
                <p className="text-sm text-text-muted md:col-span-2">
                    {t('footer.fine')}
                </p>
            </div>
        </footer>
    );
}
