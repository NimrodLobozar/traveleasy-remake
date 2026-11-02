// @vitest-environment node
// TE-010: fixtures (temp lang dirs and sources) for the key-completeness check, AC1 to AC6.
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { checkI18n } from '../../../../scripts/i18n-check';

const dirs: string[] = [];

type Fixture = {
    nl: Record<string, string>;
    en: Record<string, string>;
    files?: Record<string, string>;
    config?: object;
};

function run({ nl, en, files = {}, config }: Fixture) {
    const root = mkdtempSync(join(tmpdir(), 'i18n-check-'));

    dirs.push(root);

    const all: Record<string, string> = {
        'lang/nl.json': JSON.stringify(nl),
        'lang/en.json': JSON.stringify(en),
        ...files,
    };

    if (config) {
        all['i18n-check.config.json'] = JSON.stringify(config);
    }

    for (const [path, content] of Object.entries(all)) {
        mkdirSync(dirname(join(root, path)), { recursive: true });
        writeFileSync(join(root, path), content);
    }

    return checkI18n({ root });
}

afterEach(() => {
    for (const dir of dirs.splice(0)) {
        rmSync(dir, { recursive: true, force: true });
    }
});

const ok = { 'a.b': 'Hallo :name' };
const okEn = { 'a.b': 'Hello :name' };
const uses = { 'resources/js/x.tsx': "t('a.b');" };

describe('i18n:check', () => {
    it('passes consistent files with no warnings', () => {
        expect(run({ nl: ok, en: okEn, files: uses })).toEqual({
            errors: [],
            warnings: [],
        });
    });

    it('AC1: names each key missing from a file, in both directions', () => {
        const { errors } = run({
            nl: { ...ok, 'only.nl': 'x' },
            en: { ...okEn, 'only.en': 'y' },
            files: uses,
        });

        expect(errors).toEqual(
            expect.arrayContaining([
                expect.stringContaining('lang/en.json: missing key "only.nl"'),
                expect.stringContaining('lang/nl.json: missing key "only.en"'),
            ]),
        );
    });

    it('AC2: fails on an empty value and on a value equal to its key', () => {
        const { errors } = run({
            nl: { ...ok, 'e.mpty': '  ', 's.ame': 'v' },
            en: { ...okEn, 'e.mpty': 'x', 's.ame': 's.ame' },
            files: { ...uses, 'resources/js/y.ts': "t('e.mpty'); t('s.ame');" },
        });

        expect(errors).toEqual(
            expect.arrayContaining([
                expect.stringContaining(
                    'lang/nl.json: empty value for "e.mpty"',
                ),
                expect.stringContaining(
                    'lang/en.json: value equals the key for "s.ame"',
                ),
            ]),
        );
    });

    it('AC3: names the key when placeholders differ (case-insensitive otherwise)', () => {
        const mismatch = run({
            nl: { 'a.b': 'Hallo :naam' },
            en: okEn,
            files: uses,
        });

        expect(mismatch.errors).toEqual([
            expect.stringContaining('placeholder mismatch for "a.b"'),
        ]);
        expect(
            run({ nl: { 'a.b': 'Hallo :Name' }, en: okEn, files: uses }).errors,
        ).toEqual([]);
    });

    it('AC4: fails with file and line for a static t() or __() key with no entry', () => {
        const { errors } = run({
            nl: ok,
            en: okEn,
            files: {
                'resources/js/page.tsx':
                    "const a = 1;\nconst b = t('no.such_key');\n",
                'app/Thing.php': "<?php\n\n\n$x = __('also.missing');\n",
                'resources/views/v.blade.php':
                    "{{ __('a.b') }} @lang('view.missing')",
            },
        });

        expect(errors).toEqual(
            expect.arrayContaining([
                'resources/js/page.tsx:2: key "no.such_key" is in neither lang file',
                'app/Thing.php:4: key "also.missing" is in neither lang file',
                'resources/views/v.blade.php:1: key "view.missing" is in neither lang file',
            ]),
        );
        expect(errors).toHaveLength(3);
    });

    it('AC4: ignores dynamic keys, tests and non-calls such as foo.t()', () => {
        const { errors } = run({
            nl: ok,
            en: okEn,
            files: {
                ...uses,
                'resources/js/d.tsx':
                    'const k = `x.${y}`; t(k); t(`x.${y}`); a.t("q.r"); const s = "split(";',
                'resources/js/d.test.tsx': "t('fake.key')",
            },
        });

        expect(errors).toEqual([]);
    });

    it('AC5: reports unused keys as warnings only, and honours allow-listed prefixes', () => {
        const nl = { ...ok, 'dyn.one': 'x', 'dyn.two': 'y', 'un.used': 'z' };
        const en = { ...okEn, 'dyn.one': 'x', 'dyn.two': 'y', 'un.used': 'z' };
        const plain = run({ nl, en, files: uses });

        expect(plain.errors).toEqual([]);
        expect(
            plain.warnings.filter((w) => w.startsWith('unused key')),
        ).toHaveLength(3);

        const allowed = run({
            nl,
            en,
            files: uses,
            config: { dynamicPrefixes: ['dyn.'] },
        });

        expect(allowed.warnings).toEqual([
            expect.stringContaining('unused key "un.used"'),
        ]);
    });

    it('AC5: a key only referenced as a quoted string (e.g. in a config array) counts as used', () => {
        const { warnings } = run({
            nl: ok,
            en: okEn,
            files: {
                'resources/js/nav.ts': "export const k = { label: 'a.b' };",
            },
        });

        expect(warnings).toEqual([]);
    });

    it('warns (not fails) on English text used as a __() key', () => {
        const { errors, warnings } = run({
            nl: ok,
            en: okEn,
            files: { ...uses, 'app/C.php': "<?php __('Profile updated.');" },
        });

        expect(errors).toEqual([]);
        expect(warnings).toEqual([
            expect.stringContaining('"Profile updated."'),
        ]);
    });

    it('AC6: the real lang files pass, in well under 5 seconds', () => {
        const started = performance.now();
        const { errors } = checkI18n();

        expect(errors).toEqual([]);
        expect(performance.now() - started).toBeLessThan(5000);
    });
});
