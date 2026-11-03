import { readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vite-plus/test';
import { isLocalRequest } from './external-requests';

// Static checks for the external-request guard (TE-029 AC1); the browser proof is external-requests.spec.ts.

describe('isLocalRequest', () => {
    it.each([
        'http://localhost:8000/',
        'http://127.0.0.1:5173/@vite/client',
        'ws://localhost:5173/',
        'http://[::1]:8000/',
        'data:text/plain,hi',
        'blob:http://localhost:8000/1',
        'about:blank',
    ])('treats %s as local', (url) => {
        expect(isLocalRequest(url)).toBe(true);
    });

    it.each([
        'https://fonts.googleapis.com/css',
        'http://192.0.2.1/x',
        'https://93.184.216.34/',
        'http://localhost.evil.test/',
        'http://127.0.0.1.evil.test/',
        'http://10.0.0.5:8000/',
        'not a url',
    ])('treats %s as external', (url) => {
        expect(isLocalRequest(url)).toBe(false);
    });
});

describe('specs use the guarded test', () => {
    it('no spec imports test from @playwright/test directly', () => {
        const dir = fileURLToPath(new URL('.', import.meta.url));
        const offenders = readdirSync(dir)
            .filter((name) => name.endsWith('.spec.ts'))
            .filter((name) =>
                /import\s*\{[^}]*\btest\b[^}]*\}\s*from\s*'@playwright\/test'/.test(
                    readFileSync(dir + name, 'utf8'),
                ),
            );

        expect(offenders).toEqual([]);
    });
});
