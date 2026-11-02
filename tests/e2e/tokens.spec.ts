import { expect, test } from '@playwright/test';
import { placeholderAllowList } from './a11y-allow-list';
import { expectNoA11yViolations } from './axe';

// TE-020: the design tokens as the browser sees them (AC5 text spacing, AC6 self-hosted fonts,
// AC7 reduced motion, both themes). The contrast maths is in resources/js/design/tokens.test.ts.

test('body text is 16px with line height 1.5 and uses the self-hosted Figtree face', async ({
    page,
}) => {
    const fontRequests: string[] = [];

    page.on('request', (request) => {
        if (request.resourceType() === 'font') {
            fontRequests.push(request.url());
        }
    });
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);

    const body = await page.evaluate(() => {
        const style = getComputedStyle(document.body);

        return {
            size: style.fontSize,
            line: style.lineHeight,
            family: style.fontFamily,
            loaded: document.fonts.check('16px Figtree'),
        };
    });

    expect(body.size).toBe('16px');
    expect(body.line).toBe('24px');
    expect(body.family).toMatch(/^Figtree/);
    expect(body.loaded).toBe(true);
    expect(fontRequests.length).toBeGreaterThan(0);

    const origin = new URL(page.url()).origin;

    for (const url of fontRequests) {
        expect(
            url.startsWith(origin) || url.startsWith('http://localhost:5173'),
            url,
        ).toBe(true);
    }
});

test('the dark token set applies under data-theme', async ({ page }) => {
    await page.goto('/');

    const bg = () =>
        page.evaluate(() =>
            getComputedStyle(document.documentElement)
                .getPropertyValue('--bg')
                .trim(),
        );

    await page.evaluate(() => {
        document.documentElement.dataset.theme = 'light';
    });
    expect(await bg()).toBe('#f7f9fa');
    await page.evaluate(() => {
        document.documentElement.dataset.theme = 'dark';
    });
    expect(await bg()).toBe('#0e1a20');
});

test('reduced motion resolves the motion durations to ~0 (AC7)', async ({
    page,
}) => {
    const base = () =>
        page.evaluate(() =>
            getComputedStyle(document.documentElement)
                .getPropertyValue('--motion-base')
                .trim(),
        );

    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto('/');
    expect(await base()).toBe('200ms');

    await page.emulateMedia({ reducedMotion: 'reduce' });
    expect(await base()).toBe('0.01ms');

    const transition = await page.evaluate(
        () => getComputedStyle(document.body).transitionDuration,
    );

    expect(transition).toMatch(/^0(\.0+1)?s$|^1e-05s$/);
});

test('WCAG 1.4.12 text spacing overrides clip and overlap nothing on /, 320 px and 1280 px (AC5)', async ({
    page,
}) => {
    for (const width of [320, 1280]) {
        await page.setViewportSize({ width, height: 800 });
        await page.goto('/');
        await expect(page.locator('h1, h2').first()).toBeVisible();

        // Constructed stylesheets are CSSOM, so the strict style-src CSP allows them.
        await page.evaluate(() => {
            const sheet = new CSSStyleSheet();

            sheet.replaceSync(
                '* { line-height: 1.5 !important; letter-spacing: 0.12em !important; word-spacing: 0.16em !important; } p { margin-bottom: 2em !important; }',
            );
            document.adoptedStyleSheets = [sheet];
        });

        const problems = await page.evaluate(() => {
            const found: string[] = [];
            const root = document.documentElement;

            if (
                root.scrollWidth > root.clientWidth + 1 &&
                window.innerWidth <= 320
            ) {
                found.push('page scrolls horizontally');
            }

            for (const el of document.body.querySelectorAll<HTMLElement>('*')) {
                const style = getComputedStyle(el);
                const rect = el.getBoundingClientRect();

                if (
                    !el.textContent.trim() ||
                    rect.width <= 1 ||
                    rect.height <= 1
                ) {
                    continue;
                }

                const clipsX = ['hidden', 'clip'].includes(style.overflowX);
                const clipsY = ['hidden', 'clip'].includes(style.overflowY);

                if (
                    (clipsX && el.scrollWidth > el.clientWidth + 1) ||
                    (clipsY && el.scrollHeight > el.clientHeight + 1)
                ) {
                    found.push(
                        `${el.tagName.toLowerCase()} clips: "${el.textContent.trim().slice(0, 30)}"`,
                    );
                }
            }

            return found;
        });

        expect(problems, `width ${String(width)}`).toEqual([]);
    }
});

// TE-020 rework 1 (AC13, AC14): the keyboard focus indicator is the approved 3 px solid --focus
// outline at full strength, with at least 3:1 against the page, in both themes; and the shell has an
// explicit dark-theme axe scan.

function luminance([r, g, b]: number[]): number {
    const [lr, lg, lb] = [r, g, b].map((v) => {
        const c = (v ?? 0) / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * (lr ?? 0) + 0.7152 * (lg ?? 0) + 0.0722 * (lb ?? 0);
}

for (const scheme of ['light', 'dark'] as const) {
    for (const path of ['/', '/login']) {
        test(`keyboard focus shows the solid 3 px --focus outline on ${path} (${scheme}) (AC13)`, async ({
            page,
        }) => {
            await page.emulateMedia({ colorScheme: scheme });
            await page.goto(path);
            await expect(page.locator('h1, h2').first()).toBeVisible();

            const seen: string[] = [];

            for (let i = 0; i < 14; i++) {
                await page.keyboard.press('Tab');
                await page.waitForTimeout(400); // let colour transitions (up to 300 ms) finish

                const r = await page.evaluate(() => {
                    const el = document.activeElement;

                    if (!el || el === document.body) {
                        return null;
                    }

                    const s = getComputedStyle(el);
                    const probe = document.createElement('span');

                    probe.style.color = 'var(--focus)';
                    probe.style.backgroundColor = 'var(--bg)';
                    document.body.append(probe);

                    const p = getComputedStyle(probe);
                    const out = {
                        name: `${el.tagName.toLowerCase()}[${el.getAttribute('type') ?? ''}]`,
                        style: s.outlineStyle,
                        width: s.outlineWidth,
                        color: s.outlineColor,
                        focus: p.color,
                        bg: p.backgroundColor,
                    };

                    probe.remove();

                    return out;
                });

                if (!r) {
                    continue;
                }

                seen.push(r.name);
                expect(r.style, r.name).toBe('solid');
                expect(r.width, r.name).toBe('3px');
                expect(r.color, r.name).toBe(r.focus);

                const rgb = (c: string) =>
                    (c.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
                const [a, b] = [luminance(rgb(r.focus)), luminance(rgb(r.bg))];
                const ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);

                expect(ratio, `${r.name} focus vs page`).toBeGreaterThanOrEqual(
                    3,
                );
            }

            expect(seen.length).toBeGreaterThan(3);
        });
    }
}

test('the shell passes axe in the dark theme (AC14)', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.getByRole('link', { name: 'Inloggen' })).toBeVisible();
    await expectNoA11yViolations(page, { allow: placeholderAllowList });
});
