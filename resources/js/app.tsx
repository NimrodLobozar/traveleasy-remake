import { createInertiaApp } from '@inertiajs/react';
import { initializeTheme } from '@/hooks/use-appearance';
import { readCspNonce } from '@/lib/csp-nonce';
import { layoutFor } from '@/lib/inertia-layout';
import { pageTitle } from '@/lib/page-title';
import { withProviders } from '@/lib/with-providers';

// Browser entry. The server entry is ssr.tsx; keep the two in step (TE-007).
void createInertiaApp({
    title: pageTitle,
    layout: layoutFor,
    strictMode: true,
    withApp: withProviders,
    // Inertia's progress bar and error dialog inject <style> tags; they need the CSP nonce (TE-015).
    nonce: readCspNonce(),
    progress: {
        color: 'var(--brand)',
    },
});

// This will set light / dark mode on load...
initializeTheme();
