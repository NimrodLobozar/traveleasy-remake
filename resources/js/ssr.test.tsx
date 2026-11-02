// @vitest-environment node
// Server-side render of the app root (TE-007 quality criterion): the Inertia page object goes
// in, HTML with the page content comes out, with no browser globals around (node environment,
// so `window` and `document` don't exist, as in the SSR process).
import { readFileSync } from 'node:fs';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import { layoutFor } from '@/lib/inertia-layout';
import { pageTitle } from '@/lib/page-title';
import { withProviders } from '@/lib/with-providers';
import Welcome from '@/pages/welcome';

const translations = (locale: string) =>
    JSON.parse(readFileSync(`lang/${locale}.json`, 'utf8')) as Record<
        string,
        string
    >;

const pageFor = (locale: 'nl' | 'en') => ({
    component: 'welcome',
    props: {
        auth: { user: null },
        errors: {},
        name: 'TravelEasy',
        locale,
        translations: translations(locale),
    },
    url: '/',
    version: 'test',
});

describe('server-side rendering of the app root', () => {
    it('renders the welcome page to HTML without a browser', async () => {
        expect(typeof window).toBe('undefined');

        // Same call as ssr.tsx; without `page`, on the server, it returns the page renderer.
        const renderPage = await createInertiaApp({
            title: pageTitle,
            layout: layoutFor,
            resolve: () => Welcome,
            strictMode: true,
            withApp: withProviders,
        });
        if (!renderPage) {
            throw new Error('no page renderer');
        }

        const result = await renderPage(pageFor('en') as never, renderToString);

        expect(result.body).toContain('Let&#x27;s get started');
        expect(result.body).toContain('Log in');
        expect(result.body).toContain('data-page=');
    });

    it('renders the active locale, and the same markup every time (AC5)', async () => {
        const renderPage = await createInertiaApp({
            title: pageTitle,
            layout: layoutFor,
            resolve: () => Welcome,
            strictMode: true,
            withApp: withProviders,
        });
        if (!renderPage) {
            throw new Error('no page renderer');
        }

        const first = await renderPage(pageFor('nl') as never, renderToString);
        const second = await renderPage(pageFor('nl') as never, renderToString);

        expect(first.body).toContain('Aan de slag');
        expect(first.body).toContain('Inloggen');
        expect(first.body).not.toContain('Let&#x27;s get started');
        expect(second.body).toBe(first.body);
    });
});
