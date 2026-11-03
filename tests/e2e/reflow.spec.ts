import { expect, test } from './base-test';
import type { Page } from '@playwright/test';

// Dutch strings must neither overflow the page nor be clipped (WCAG 1.4.4 resize text, 1.4.10 reflow).
// TE-009 follow-up requested by the accessibility audit.

const pages = ['/', '/login', '/register'];

/** Returns descriptions of elements that clip their own text (hidden overflow) or poke out of the viewport. */
async function findOverflow(page: Page): Promise<string[]> {
    return page.evaluate(() => {
        const problems: string[] = [];
        const root = document.documentElement;

        if (root.scrollWidth > root.clientWidth) {
            problems.push(
                `page scrolls horizontally (${String(root.scrollWidth)} > ${String(root.clientWidth)})`,
            );
        }

        for (const el of document.body.querySelectorAll<HTMLElement>('*')) {
            const style = getComputedStyle(el);

            if (style.display === 'none' || el.offsetParent === null) {
                continue;
            }

            if (!el.textContent.trim()) {
                continue;
            }

            if (el.getBoundingClientRect().width <= 1) {
                continue; // visually hidden (sr-only) text is meant to be clipped
            }

            const clips = ['hidden', 'clip'].includes(style.overflowX);
            const ellipsis = style.textOverflow === 'ellipsis';

            if ((clips || ellipsis) && el.scrollWidth > el.clientWidth + 1) {
                problems.push(
                    `${el.tagName.toLowerCase()} clips text: "${el.textContent.trim().slice(0, 40)}"`,
                );
            }

            const rect = el.getBoundingClientRect();

            if (
                rect.right > root.clientWidth + 1 &&
                style.position !== 'fixed' &&
                style.position !== 'absolute'
            ) {
                problems.push(
                    `${el.tagName.toLowerCase()} extends past the viewport: "${el.textContent.trim().slice(0, 40)}"`,
                );
            }
        }

        return problems;
    });
}

for (const path of pages) {
    test(`${path} in Dutch has no overflow or clipping at 320 px`, async ({
        page,
    }) => {
        await page.setViewportSize({ width: 320, height: 640 });
        await page.goto(path);
        await expect(page.locator('html')).toHaveAttribute('lang', 'nl');
        await expect(page.locator('h1, h2').first()).toBeVisible();

        expect(await findOverflow(page)).toEqual([]);
    });

    test(`${path} in Dutch has no clipping at 200% text size`, async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(path);
        await expect(page.locator('h1, h2').first()).toBeVisible();
        await page.evaluate(() => {
            document.documentElement.style.fontSize = '200%';
        });

        expect(await findOverflow(page)).toEqual([]);
    });
}
