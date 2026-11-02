// @vitest-environment node
// Guards AC8: every key used by `t('...')` in the UI exists in both lang files, and the two files
// carry the same keys, sorted. (TE-010 adds the CI completeness check for the whole app; this one
// keeps the frontend honest in the meantime.)
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';

const root = process.cwd();

function readLang(locale: string): Record<string, string> {
    return JSON.parse(
        readFileSync(join(root, 'lang', `${locale}.json`), 'utf8'),
    ) as Record<string, string>;
}

function sourceFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            return ['actions', 'routes', 'wayfinder', 'types'].includes(entry)
                ? []
                : sourceFiles(path);
        }

        return /\.tsx?$/.test(entry) && !/\.test\.tsx?$/.test(entry)
            ? [path]
            : [];
    });
}

const en = readLang('en');
const nl = readLang('nl');

describe('lang files', () => {
    it('have the same keys in the same sorted order', () => {
        expect(Object.keys(nl)).toEqual(Object.keys(en));
        expect(Object.keys(en)).toEqual(Object.keys(en).toSorted());
    });

    it('define every key the UI uses via t()', () => {
        const used = new Set<string>();

        for (const file of sourceFiles(join(root, 'resources/js'))) {
            for (const match of readFileSync(file, 'utf8').matchAll(
                /\bt\(\s*'([a-z0-9_.]+)'/g,
            )) {
                used.add(match[1] as string);
            }
        }

        expect(used.size).toBeGreaterThan(50);
        expect([...used].filter((key) => !(key in en))).toEqual([]);
        expect([...used].filter((key) => !(key in nl))).toEqual([]);
    });

    it('keep the same placeholders per key and no markup in values', () => {
        const placeholders = (text: string) =>
            [...text.matchAll(/:([A-Za-z_]\w*)/g)]
                .map((m) => (m[1] as string).toLowerCase())
                .toSorted();

        for (const [key, value] of Object.entries(en)) {
            expect(placeholders(nl[key] ?? ''), key).toEqual(
                placeholders(value),
            );
            expect(value, key).not.toMatch(/[<>]/);
            expect(nl[key], key).not.toMatch(/[<>]/);
        }
    });
});
