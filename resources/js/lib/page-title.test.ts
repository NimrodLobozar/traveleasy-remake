import { describe, expect, it } from 'vite-plus/test';
import { pageTitle } from './page-title';

describe('pageTitle', () => {
    it('appends the app name to a page title with a pipe', () => {
        expect(pageTitle('Dashboard')).toMatch(/^Dashboard \| .+/);
    });

    it('falls back to the app name alone', () => {
        expect(pageTitle('')).not.toContain('|');
    });
});
