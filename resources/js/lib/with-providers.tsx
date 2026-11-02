import type { ReactElement } from 'react';
import RouteFocus from '@/components/shell/route-focus';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';

/** App-wide providers around the Inertia app; shared by app.tsx and ssr.tsx so the markup matches. */
export function withProviders(app: ReactElement): ReactElement {
    return (
        <TooltipProvider delayDuration={0}>
            {app}
            <RouteFocus />
            <Toaster />
        </TooltipProvider>
    );
}
