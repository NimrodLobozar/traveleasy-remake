import { useTranslation } from '@/lib/i18n';

/** First focusable element: off-screen until focused (never display:none), then jumps to <main>. */
export default function SkipLink() {
    const { t } = useTranslation();

    return (
        <a
            href="#main"
            className="absolute top-2 -left-[9999px] z-50 rounded-md bg-brand px-4 py-3 font-bold text-on-brand focus:left-4"
        >
            {t('shell.skip')}
        </a>
    );
}
