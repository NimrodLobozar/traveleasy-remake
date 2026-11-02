#!/usr/bin/env node
// i18n key-completeness check (TE-010). Reads files only; no network.
//
//   npm run i18n:check            (exit 1 on any error, warnings never fail)
//
// Errors: a key missing from one lang file; an empty value or a value equal to its key; a placeholder
// (`:name`) that differs between the files; a static t('key') / __('key') call whose key is in neither
// file. Warnings: a key no source file references (dynamic key prefixes are allow-listed in
// i18n-check.config.json); a __('English text') call with no entry.
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

type Json = Record<string, unknown>;
type Strings = Record<string, string>;

export type CheckResult = { errors: string[]; warnings: string[] };

const LOCALES = ['nl', 'en'] as const;
const SKIP_DIRS = new Set([
    'node_modules',
    'vendor',
    'actions',
    'routes',
    'wayfinder',
    'generated',
]);
// Sources scanned for static calls. `js` = t('key'); `php` = __('key'), trans(), trans_choice(), @lang().
const SOURCES: { dir: string; ext: RegExp; kind: 'js' | 'php' }[] = [
    { dir: 'resources/js', ext: /\.tsx?$/, kind: 'js' },
    { dir: 'app', ext: /\.php$/, kind: 'php' },
    { dir: 'resources/views', ext: /\.php$/, kind: 'php' },
];
const CALL = {
    js: /(?<![\w.$])t\(\s*(['"`])([^'"`$\\\n]+)\1/g,
    php: /(?<![\w>:$])(?:__|trans|trans_choice)\(\s*(['"])([^'"\\\n]+)\1|@lang\(\s*(['"])([^'"\\\n]+)\3/g,
};
const KEY_SHAPE = /^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/;
const QUOTED_KEY = /['"`]([a-z0-9_]+(?:\.[a-z0-9_]+)+)['"`]/g;

function walk(dir: string, ext: RegExp): string[] {
    if (!existsSync(dir)) {
        return [];
    }

    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            return SKIP_DIRS.has(entry) ? [] : walk(path, ext);
        }

        // Tests may use fake keys on purpose; the key-usage scan is for shipped code.
        return ext.test(entry) && !/\.(test|spec)\.tsx?$/.test(entry)
            ? [path]
            : [];
    });
}

function placeholders(text: string): string[] {
    return [...text.matchAll(/:([A-Za-z_]\w*)/g)]
        .map((m) => (m[1] ?? '').toLowerCase())
        .sort();
}

function readJson(path: string, errors: string[]): Json {
    try {
        return JSON.parse(readFileSync(path, 'utf8')) as Json;
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        errors.push(`${path}: cannot read or parse (${message})`);

        return {};
    }
}

export function checkI18n({
    root = process.cwd(),
    langDir = 'lang',
    configFile = 'i18n-check.config.json',
}: { root?: string; langDir?: string; configFile?: string } = {}): CheckResult {
    const errors: string[] = [];
    const warnings: string[] = [];
    const rel = (path: string) => relative(root, path).replaceAll('\\', '/');

    const configPath = join(root, configFile);
    const config = existsSync(configPath) ? readJson(configPath, errors) : {};
    const dynamicPrefixes = Array.isArray(config.dynamicPrefixes)
        ? (config.dynamicPrefixes as string[])
        : [];

    const files = Object.fromEntries(
        LOCALES.map((locale) => [
            locale,
            readJson(join(root, langDir, `${locale}.json`), errors),
        ]),
    ) as Record<(typeof LOCALES)[number], Strings>;
    const [first, second] = LOCALES;

    // AC1: keys missing from one file.
    for (const [from, to] of [
        [first, second],
        [second, first],
    ] as const) {
        for (const key of Object.keys(files[from])) {
            if (!(key in files[to])) {
                errors.push(
                    `${langDir}/${to}.json: missing key "${key}" (present in ${langDir}/${from}.json)`,
                );
            }
        }
    }

    for (const locale of LOCALES) {
        for (const [key, value] of Object.entries(files[locale])) {
            // AC2: empty value or value equal to the key.
            if (typeof value !== 'string' || value.trim() === '') {
                errors.push(
                    `${langDir}/${locale}.json: empty value for "${key}"`,
                );
            } else if (value === key) {
                errors.push(
                    `${langDir}/${locale}.json: value equals the key for "${key}"`,
                );
            }
        }
    }

    // AC3: placeholders.
    for (const key of Object.keys(files[first])) {
        const a = files[first][key];
        const b = files[second][key];

        if (typeof a === 'string' && typeof b === 'string') {
            const pa = placeholders(a).join(', ');
            const pb = placeholders(b).join(', ');

            if (pa !== pb) {
                errors.push(
                    `placeholder mismatch for "${key}": ${first} [${pa}] vs ${second} [${pb}]`,
                );
            }
        }
    }

    // AC4 + AC5: scan sources.
    const referenced = new Set();
    const known = (key: string) =>
        LOCALES.some((locale) => key in files[locale]);

    for (const { dir, ext, kind } of SOURCES) {
        for (const file of walk(join(root, dir), ext)) {
            const text = readFileSync(file, 'utf8');
            const lineOf = (index: number) =>
                text.slice(0, index).split('\n').length;

            for (const m of text.matchAll(QUOTED_KEY)) {
                referenced.add(m[1]);
            }

            for (const m of text.matchAll(CALL[kind])) {
                const key = m[2] ?? m[4] ?? '';

                referenced.add(key);

                if (known(key)) {
                    continue;
                }

                const where = `${rel(file)}:${String(lineOf(m.index))}`;

                if (KEY_SHAPE.test(key)) {
                    errors.push(
                        `${where}: key "${key}" is in neither lang file`,
                    );
                } else {
                    // English text as the key (Laravel's JSON style) has no per-language entry yet.
                    warnings.push(
                        `${where}: "${key}" is not a key and has no entry in the lang files`,
                    );
                }
            }
        }
    }

    for (const key of Object.keys(files[first])) {
        if (
            !referenced.has(key) &&
            !dynamicPrefixes.some((prefix) => key.startsWith(prefix))
        ) {
            warnings.push(
                `unused key "${key}" (no source references it; allow-list a dynamic prefix in ${configFile})`,
            );
        }
    }

    return { errors, warnings };
}

const isCli =
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (isCli) {
    const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
    const started = performance.now();
    const { errors, warnings } = checkI18n({ root });

    for (const warning of warnings) {
        process.stderr.write(`warning: ${warning}
`);
    }

    for (const error of errors) {
        process.stderr.write(`error: ${error}
`);
    }

    const ms = Math.round(performance.now() - started);

    process.stdout.write(
        `i18n:check ${errors.length === 0 ? 'passed' : 'FAILED'}: ${String(errors.length)} error(s), ${String(warnings.length)} warning(s), ${String(ms)} ms\n`,
    );
    process.exit(errors.length === 0 ? 0 : 1);
}
