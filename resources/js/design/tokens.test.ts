// @vitest-environment node
// TE-020: unit tests over resources/css/tokens.css, the single source of the design tokens.
// AC3 (no raw hex outside the token file), AC4/AC10/AC11 (WCAG contrast of every allowed pair in both
// themes), AC7 (one reduced-motion rule), AC5 (rem type scale), AC6 (self-hosted fonts).
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';

const root = process.cwd();
const tokensCss = readFileSync(join(root, 'resources/css/tokens.css'), 'utf8');

type Theme = 'light' | 'dark';

/** Body of the first innermost rule whose selector (text before `{`) matches. */
function block(css: string, selector: RegExp): string {
    for (const m of css.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
        if (selector.test((m[1] as string).trim())) {
            return m[2] as string;
        }
    }

    throw new Error(`no rule for ${String(selector)}`);
}

function vars(body: string): Record<string, string> {
    return Object.fromEntries(
        [...body.matchAll(/--([a-z0-9-]+):\s*([^;]+);/g)].map((m) => [
            m[1] as string,
            (m[2] as string).replace(/\s+/g, ' ').trim(),
        ]),
    );
}

const light = vars(block(tokensCss, /^:root$/));
const dark = vars(
    block(tokensCss, /^:root\.dark,\s*:root\[data-theme='dark'\]$/),
);
const darkMedia = vars(
    block(tokensCss, /^:root:not\(\[data-theme\]\):not\(\.dark\)$/),
);
const themes: Record<Theme, Record<string, string>> = { light, dark };

function luminance(hex: string): number {
    const m = /^#([0-9a-f]{6})$/i.exec(hex);

    if (!m) {
        throw new Error(`not a #rrggbb colour: ${hex}`);
    }

    const [r, g, b] = [0, 2, 4].map((i) => {
        const c = parseInt((m[1] as string).slice(i, i + 2), 16) / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    }) as [number, number, number];

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a: string, b: string): number {
    const [hi, lo] = [luminance(a), luminance(b)].toSorted((x, y) => y - x) as [
        number,
        number,
    ];

    return (hi + 0.05) / (lo + 0.05);
}

type Pair = { fg: string; bg: string; min: number; use: string };

const pairs = (
    fgs: string[],
    bgs: string[],
    min: number,
    use: string,
): Pair[] => fgs.flatMap((fg) => bgs.map((bg) => ({ fg, bg, min, use })));

const surfaces = ['bg', 'surface', 'surface-2'];

// The allowed pairs: docs/design/system.md section 2. Add a pair here to allow it; a pair that does
// not meet its threshold fails the test.
const allowedPairs: Pair[] = [
    ...pairs(['text', 'text-muted'], surfaces, 4.5, 'body and secondary text'),
    ...pairs(['brand', 'brand-hover'], surfaces, 4.5, 'links'),
    ...pairs(['brand-active'], ['bg', 'surface'], 4.5, 'pressed link'),
    ...pairs(
        ['on-brand'],
        ['brand', 'brand-hover', 'brand-active'],
        4.5,
        'text on brand fill, incl. a highlighted listbox option (AC11)',
    ),
    ...pairs(
        ['on-danger'],
        ['danger', 'danger-hover', 'danger-active'],
        4.5,
        'text on danger fill',
    ),
    ...pairs(
        ['success', 'warning', 'danger', 'info'],
        surfaces,
        4.5,
        'status text and icon',
    ),
    ...pairs(
        ['border'],
        surfaces,
        3,
        'control, input and checkbox border (AC10), border on surface-2 (AC11)',
    ),
    ...pairs(['focus'], surfaces, 3, 'focus ring'),
];

describe('token file', () => {
    it('defines every token used by the pair list in both themes', () => {
        const names = new Set(allowedPairs.flatMap((p) => [p.fg, p.bg]));

        for (const theme of ['light', 'dark'] as const) {
            for (const name of names) {
                expect(themes[theme][name], `${theme} --${name}`).toMatch(
                    /^#[0-9a-f]{6}$/i,
                );
            }
        }
    });

    it('keeps the no-JavaScript dark block identical to the dark theme block', () => {
        expect(darkMedia).toEqual(dark);
    });

    it('changes the same colour tokens in both themes', () => {
        const colours = (v: Record<string, string>) =>
            Object.keys(v)
                .filter((k) => v[k]?.startsWith('#'))
                .toSorted();

        expect(colours(dark)).toEqual(colours(light));
    });

    it('maps the shadcn input and ring names onto the 3:1 border and the focus token (AC10)', () => {
        expect(tokensCss).toMatch(/--color-input:\s*var\(--border\)/);
        expect(tokensCss).toMatch(/--color-ring:\s*var\(--focus\)/);
    });
});

describe.each(['light', 'dark'] as const)('contrast, %s theme', (theme) => {
    const t = themes[theme];

    it.each(allowedPairs)(
        '--$fg on --$bg is at least $min:1 ($use)',
        ({ fg, bg, min }) => {
            const ratio = contrast(t[fg] as string, t[bg] as string);

            expect(
                ratio,
                `${theme}: --${fg} on --${bg} = ${ratio.toFixed(2)}:1`,
            ).toBeGreaterThanOrEqual(min);
        },
    );
});

describe('type, motion and fonts', () => {
    it('keeps the body at 16px with line height 1.5, all sizes in rem', () => {
        expect(tokensCss).toMatch(/--text-base:\s*1rem;/);
        expect(tokensCss).toMatch(/--text-base--line-height:\s*1\.5;/);

        const sizes = [
            ...tokensCss.matchAll(
                /--text-(?:xs|sm|base|lg|xl|2xl|3xl|4xl):\s*([^;]+);/g,
            ),
        ];

        expect(sizes).toHaveLength(8);

        for (const m of sizes) {
            expect(m[1], 'type sizes use rem').toMatch(/rem/);
            expect(m[1], 'no px sizes').not.toMatch(/\bpx\b/);
        }
    });

    it('has one reduced-motion block that zeroes the durations (AC7)', () => {
        expect(
            tokensCss.match(/@media \(prefers-reduced-motion: reduce\)/g),
        ).toHaveLength(1);

        const rule = tokensCss.slice(
            tokensCss.indexOf('prefers-reduced-motion'),
        );

        for (const name of ['fast', 'base', 'slow']) {
            expect(rule).toMatch(new RegExp(`--motion-${name}:\\s*0\\.01ms`));
        }

        expect(rule).toMatch(/animation-iteration-count:\s*1/);
        expect(rule).toMatch(/scroll-behavior:\s*auto/);
    });

    it('self-hosts Figtree as woff2 with font-display swap and no remote URL (AC6)', () => {
        const faces = [...tokensCss.matchAll(/@font-face\s*\{([^}]*)\}/g)].map(
            (m) => m[1] as string,
        );

        expect(faces.length).toBeGreaterThanOrEqual(1);

        for (const face of faces) {
            expect(face).toMatch(/font-display:\s*swap/);
            expect(face).not.toMatch(/https?:\/\//);

            const file = /url\('\.\.\/fonts\/([^']+\.woff2)'\)/.exec(face)?.[1];

            expect(file, 'woff2 under resources/fonts').toBeDefined();
            expect(
                existsSync(join(root, 'resources/fonts', file as string)),
            ).toBe(true);
        }

        expect(existsSync(join(root, 'resources/fonts/OFL.txt'))).toBe(true);
    });

    it('keeps the font payload under 120 KB', () => {
        const total = readdirSync(join(root, 'resources/fonts'))
            .filter((f) => f.endsWith('.woff2'))
            .reduce(
                (sum, f) =>
                    sum + statSync(join(root, 'resources/fonts', f)).size,
                0,
            );

        expect(total).toBeLessThan(120 * 1024);
    });

    it('keeps the inline html background in app.blade.php equal to --bg', () => {
        const blade = readFileSync(
            join(root, 'resources/views/app.blade.php'),
            'utf8',
        );

        expect(blade).toContain(`background-color: ${String(light.bg)}`);
        expect(blade).toContain(`background-color: ${String(dark.bg)}`);
    });
});

// AC3: no raw hex colours outside the token file.
const skipDirs = new Set([
    'actions',
    'routes',
    'wayfinder',
    'types',
    'node_modules',
]);
// harness.test.tsx: a deliberately low-contrast fixture for the axe harness test.
// tokens.test.ts: this file names no hex, but keep it out of its own scan.
const hexAllowList = new Set([
    'resources/js/test/harness.test.tsx',
    'resources/js/design/tokens.test.ts',
]);

function scan(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            return skipDirs.has(entry) ? [] : scan(path);
        }

        return /\.(tsx?|css|blade\.php)$/.test(entry) ? [path] : [];
    });
}

describe('components use tokens, not hard-coded colours or hidden focus (AC12, AC13)', () => {
    it('hard-codes no text-white or text-black and hides no outline', () => {
        const offenders: string[] = [];
        const rules: [RegExp, string][] = [
            [
                /\btext-(white|black)\b/,
                'hard-coded text-white/black (use a token pair)',
            ],
            [
                /\bdark:bg-destructive\/\d+/,
                'translucent destructive fill (contrast); use the token',
            ],
            [
                /(^|[\s"'`:])(focus(-visible)?:)?outline-(none|hidden)\b/,
                'outline removed (the global 3 px --focus outline must show)',
            ],
            [
                /(focus|focus-visible):ring-/,
                'ring used as the focus indicator instead of the --focus outline',
            ],
        ];

        for (const file of scan(join(root, 'resources/js'))) {
            const rel = relative(root, file).replaceAll('\\', '/');

            if (
                !rel.endsWith('.tsx') ||
                rel.endsWith('.test.tsx') ||
                hexAllowList.has(rel)
            ) {
                continue;
            }

            readFileSync(file, 'utf8')
                .split('\n')
                .forEach((line, i) => {
                    for (const [re, why] of rules) {
                        if (re.test(line)) {
                            offenders.push(`${rel}:${String(i + 1)}: ${why}`);
                        }
                    }
                });
        }

        expect(offenders).toEqual([]);
    });
});

describe('no raw hex colours outside the token file (AC3)', () => {
    it('finds none in resources/js, resources/css and resources/views', () => {
        const offenders: string[] = [];

        for (const dir of [
            'resources/js',
            'resources/css',
            'resources/views',
        ]) {
            for (const file of scan(join(root, dir))) {
                const rel = relative(root, file).replaceAll('\\', '/');

                if (
                    rel === 'resources/css/tokens.css' ||
                    rel === 'resources/views/app.blade.php' || // checked against the tokens above
                    rel === 'resources/views/errors/minimal.blade.php' || // vendor error view
                    hexAllowList.has(rel)
                ) {
                    continue;
                }

                readFileSync(file, 'utf8')
                    .split('\n')
                    .forEach((line, i) => {
                        if (/#[0-9a-fA-F]{3,8}\b/.test(line)) {
                            offenders.push(`${rel}:${String(i + 1)}`);
                        }
                    });
            }
        }

        expect(offenders).toEqual([]);
    });
});
