import type { ReactNode } from 'react';
import SiteFooter from '@/components/shell/site-footer';
import SiteHeader from '@/components/shell/site-header';
import SkipLink from '@/components/shell/skip-link';

/** The public page shell (TE-016): skip link, header, main, footer. Pages bring their own single <h1>. */
export default function ShellLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-svh flex-col bg-bg text-text">
            <SkipLink />
            <SiteHeader />
            <main
                id="main"
                tabIndex={-1}
                className="scroll-mt-24 outline-offset-[-3px]"
            >
                {children}
            </main>
            <SiteFooter />
        </div>
    );
}
