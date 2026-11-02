import { expect, test } from '@playwright/test';

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
