import { createInertiaApp } from '@inertiajs/react';
import { initializeTheme } from '@/hooks/use-appearance';
import { layoutFor } from '@/lib/inertia-layout';
import { pageTitle } from '@/lib/page-title';
import { withProviders } from '@/lib/with-providers';

// Browser entry. The server entry is ssr.tsx; keep the two in step (TE-007).
void createInertiaApp({
    title: pageTitle,
    layout: layoutFor,
    strictMode: true,
    withApp: withProviders,
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
