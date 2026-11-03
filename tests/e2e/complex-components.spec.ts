import type { Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {
    formatViolations,
    WCAG_AA_TAGS,
} from '../../resources/js/test/axe-config';
import { expectNoA11yViolations } from './axe';
import { expect, test } from './base-test';

// TE-023: combobox, date picker, table, dialog, toast and pagination on the dev showcase (/design):
// axe in both themes at 320 and 1280 px (also with each overlay open), targets, focus ring and
// focus not obscured, zoom and text spacing, forced colours, reflow, keyboard flows, and no
// hydration warning.

/** Waits until React has attached to the page (a hydrated element carries React's fibre key). */
async function openDesign(page: Page) {
    await page.goto('/design');
    await page.waitForFunction(() => {
        const element = document.querySelector('input[role="combobox"]');

        return (
            element !== null &&
            Object.keys(element).some((key) => key.startsWith('__reactFiber'))
        );
    });
}

/**
 * axe on the open combobox widget only (input and list). While a list is open, React Aria hides the
 * rest of the page from assistive technology with aria-hidden; that content stays focusable, so
 * axe's aria-hidden-focus (which has no exemption for a non-modal popup) fires on the hidden
 * siblings. The page itself is scanned closed above; no rule is disabled.
 */
async function expectOpenComboboxNoViolations(page: Page) {
    const results = await new AxeBuilder({ page })
        .include('input[role="combobox"][aria-expanded="true"]')
        .include('[role="listbox"]')
        .withTags([...WCAG_AA_TAGS])
        .analyze();
    expect(
        results.violations.length,
        formatViolations(results.violations, '/design'),
    ).toBe(0);
}

const SPACING =
    '*{line-height:1.5 !important;letter-spacing:.12em !important;word-spacing:.16em !important}p{margin-bottom:2em !important}';

async function applyTextSpacing(page: Page) {
    // Constructed stylesheets are not blocked by the CSP (addStyleTag would be).
    await page.evaluate((css) => {
        const sheet = new CSSStyleSheet();
        sheet.replaceSync(css);
        document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet];
    }, SPACING);
}

for (const width of [320, 1280]) {
    for (const scheme of ['light', 'dark'] as const) {
        test.describe(`${String(width)}px, ${scheme}`, () => {
            test.beforeEach(async ({ page }) => {
                await page.setViewportSize({ width, height: 900 });
                await page.emulateMedia({ colorScheme: scheme });
                await openDesign(page);
            });

            test('every component and state passes axe, no page-level scroll (AC8)', async ({
                page,
            }) => {
                await expectNoA11yViolations(page);
                const overflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth >
                        document.documentElement.clientWidth,
                );
                expect(overflow).toBe(false);
            });

            test('combobox open (with options and without) passes axe', async ({
                page,
            }) => {
                const input = page.getByRole('combobox', {
                    name: 'Departure airport',
                });
                await input.click();
                await page.keyboard.type('mal');
                await expect(page.getByRole('option')).toHaveCount(2);
                await expectOpenComboboxNoViolations(page);
                await input.fill('');
                await page.keyboard.type('zzz');
                await expect(
                    page.getByText('Geen resultaten voor "zzz"'),
                ).toBeVisible();
                await expectOpenComboboxNoViolations(page);
            });

            test('date picker calendar open passes axe', async ({ page }) => {
                await page
                    .getByRole('button', { name: 'Kalender openen' })
                    .first()
                    .click();
                await expect(
                    page.getByRole('dialog', { name: 'Kalender' }),
                ).toBeVisible();
                await expectNoA11yViolations(page);
            });

            test('dialog open passes axe', async ({ page }) => {
                await page
                    .getByRole('button', { name: 'Cancel booking' })
                    .first()
                    .click();
                await expect(page.getByRole('alertdialog')).toBeVisible();
                await expectNoA11yViolations(page);
            });

            test('toasts (confirmation and error) pass axe', async ({
                page,
            }) => {
                await page
                    .getByRole('button', {
                        name: 'Show confirmation',
                        exact: true,
                    })
                    .click();
                await page
                    .getByRole('button', { name: 'Show error', exact: true })
                    .click();
                await expect(page.locator('[data-toast]')).toHaveCount(2);
                await expectNoA11yViolations(page);
            });
        });
    }
}

test('no hydration warning or page error on /design (AC9)', async ({
    page,
}) => {
    const problems: string[] = [];
    page.on('console', (message) => {
        if (/hydrat|did not match|Warning:/i.test(message.text())) {
            problems.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        problems.push(error.message);
    });
    await openDesign(page);
    await page.getByRole('button', { name: 'Kalender openen' }).first().click();
    await expect(page.getByRole('dialog', { name: 'Kalender' })).toBeVisible();
    expect(problems).toEqual([]);
});

test('interactive elements are at least 24 px (AC8)', async ({ page }) => {
    await openDesign(page);
    const small = await page.evaluate(() =>
        [
            ...document.querySelectorAll<HTMLElement>(
                'section[aria-labelledby="d-combo"] button, section[aria-labelledby="d-date"] button, section[aria-labelledby="d-dialog"] button, section[aria-labelledby="d-toast"] button, th button, nav[aria-label="Paginering"] a, input[role="combobox"], input[inputmode="numeric"]',
            ),
        ]
            .filter((el) => el.offsetParent !== null && !el.closest('.sr-only'))
            .map((el) => ({ el, rect: el.getBoundingClientRect() }))
            .filter(({ rect }) => rect.width < 24 || rect.height < 24)
            .map(({ el }) => el.outerHTML.slice(0, 80)),
    );
    expect(small).toEqual([]);
});

test.describe('coarse pointer', () => {
    test.use({
        hasTouch: true,
        isMobile: true,
        viewport: { width: 390, height: 844 },
    });

    test('controls, sort buttons, pagination and rows are at least 44 px (AC8)', async ({
        page,
    }) => {
        await openDesign(page);
        const result = await page.evaluate(() => {
            const heights = (selector: string) =>
                [...document.querySelectorAll<HTMLElement>(selector)]
                    .filter((el) => el.offsetParent !== null)
                    .map((el) => Math.round(el.getBoundingClientRect().height));
            return {
                coarse: window.matchMedia('(pointer: coarse)').matches,
                sort: heights('th button'),
                pages: heights(
                    'nav[aria-label="Paginering"] a, nav[aria-label="Paginering"] [role="link"]',
                ),
                inputs: heights(
                    'input[role="combobox"], input[inputmode="numeric"]',
                ),
                toggles: heights('input[role="combobox"] + button'),
            };
        });
        expect(result.coarse).toBe(true);
        for (const group of [result.sort, result.pages, result.inputs]) {
            expect(group.length).toBeGreaterThan(0);
            expect(Math.min(...group)).toBeGreaterThanOrEqual(44);
        }
    });

    test('dense staff table rows are 44 px on touch (spec 4.14)', async ({
        page,
    }) => {
        await openDesign(page);
        const rowHeight = await page
            .locator('table')
            .nth(1)
            .locator('tbody tr')
            .first()
            .evaluate((el) => el.getBoundingClientRect().height);
        expect(rowHeight).toBeGreaterThanOrEqual(44);
    });
});

test('dense staff table rows are 40 px with a precise pointer (spec 4.14)', async ({
    page,
}) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await openDesign(page);
    const rowHeight = await page
        .locator('table')
        .nth(1)
        .locator('tbody tr')
        .first()
        .evaluate((el) => el.getBoundingClientRect().height);
    expect(rowHeight).toBeGreaterThanOrEqual(40);
    expect(rowHeight).toBeLessThan(44);
});

test('keyboard focus ring (3 px) on every new control (AC8)', async ({
    page,
}) => {
    await openDesign(page);
    const targets = [
        page.getByRole('combobox', { name: 'Departure airport' }),
        page.getByRole('button', { name: 'Kalender openen' }).first(),
        page.getByRole('button', { name: 'Flight', exact: true }).first(),
        page.getByRole('link', { name: 'Volgende' }).first(),
        page.getByRole('button', { name: 'Show confirmation', exact: true }),
        page.getByRole('button', { name: 'Cancel booking' }).first(),
    ];
    for (const target of targets) {
        await target.focus();
        await page.keyboard.press('Shift+Tab');
        await page.keyboard.press('Tab');
        const outline = await target.evaluate((el) => {
            const style = getComputedStyle(el);

            return { width: style.outlineWidth, style: style.outlineStyle };
        });
        expect(outline).toEqual({ width: '3px', style: 'solid' });
    }
});

test('combobox: type, arrows, Enter, Escape, toggle button (AC1)', async ({
    page,
}) => {
    await openDesign(page);
    const input = page.getByRole('combobox', { name: 'Departure airport' });
    await input.click();
    await page.keyboard.type('mal');
    await expect(input).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('ArrowDown');
    const active = await input.getAttribute('aria-activedescendant');
    expect(active).not.toBeNull();
    await expect(page.locator(`[id="${active ?? ''}"]`)).toHaveText(/Málaga/);
    await page.keyboard.press('Enter');
    await expect(input).toHaveValue('Málaga AGP');
    await expect(input).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(input).toHaveValue('');
    // Clearing re-opens the list (RAC opens it when the text changes); Escape closes it again.
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Show airports' }).click();
    await expect(page.getByRole('option')).toHaveCount(5);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('listbox')).toHaveCount(0);
});

test('combobox announces the count through one live region (AC1)', async ({
    page,
}) => {
    await openDesign(page);
    const input = page.getByRole('combobox', { name: 'Departure airport' });
    await input.click();
    await page.keyboard.type('mal');
    const announcer = page.locator('[data-live-announcer]');
    await expect(announcer).toContainText(/2 opties beschikbaar/);
});

test('combobox async: loading status, focus stays in the input (AC1)', async ({
    page,
}) => {
    await openDesign(page);
    const input = page.getByRole('combobox', {
        name: 'Destination (loads from the server)',
    });
    await input.click();
    await page.keyboard.type('m');
    await expect(page.getByText('Resultaten laden…').first()).toBeAttached();
    await expect(input).toBeFocused();
    await expect(input).toHaveAttribute('aria-busy', 'true');
    await expect(page.getByRole('option')).toHaveCount(3);
    await expect(input).not.toHaveAttribute('aria-busy', 'true');
    await expect(input).toBeFocused();
});

test('combobox: text that matches nothing is reported on blur (spec 4.5)', async ({
    page,
}) => {
    await openDesign(page);
    const input = page.getByRole('combobox', { name: 'Departure airport' });
    await input.click();
    await page.keyboard.type('Mars');
    await page.keyboard.press('Tab');
    await expect(
        page.getByText(
            'Choose an airport from the list, for example Malta (MLA).',
            { exact: true },
        ),
    ).toBeVisible();
    await expect(input).toHaveValue('Mars');
});

test('date picker: typing, error, calendar keyboard model, Escape returns focus (AC2)', async ({
    page,
}) => {
    await openDesign(page);
    const input = page.getByRole('textbox', { name: 'Departure date' });
    await input.fill('12-06-2027');
    await page.keyboard.press('Tab');
    await expect(input).not.toHaveAttribute('aria-invalid', 'true');
    await input.fill('01-01-2027');
    await page.keyboard.press('Tab');
    await expect(
        page.getByText('Kies een datum vanaf 7 juni 2027.', { exact: true }),
    ).toBeVisible();
    await expect(input).toHaveValue('01-01-2027');
    await input.fill('29-02-2027');
    await page.keyboard.press('Tab');
    await expect(page.getByText(/^Die datum bestaat niet/)).toBeVisible();

    await input.fill('12-06-2027');
    const opener = page
        .getByRole('button', { name: 'Kalender openen' })
        .first();
    await opener.click();
    const dialog = page.getByRole('dialog', { name: 'Kalender' });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('PageDown');
    await expect(
        dialog.getByRole('heading', { name: /juli 2027/i }).first(),
    ).toBeAttached();
    await page.keyboard.press('Home');
    await page.keyboard.press('End');
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(opener).toBeFocused();

    await opener.click();
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('Enter');
    await expect(dialog).toHaveCount(0);
    await expect(input).toHaveValue('13-06-2027');
    await expect(
        page.locator('input[type="hidden"][name="departure"]'),
    ).toHaveValue('2027-06-13');
});

test('date picker: week starts on Monday, unavailable day is disabled (AC2)', async ({
    page,
}) => {
    await openDesign(page);
    await page
        .getByRole('textbox', { name: 'Departure date' })
        .fill('12-06-2027');
    await page.getByRole('button', { name: 'Kalender openen' }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Kalender' });
    const monday = dialog.getByRole('button', {
        name: /^maandag 14 juni 2027/,
    });
    const firstInRow = await monday.evaluate(
        (el) => el.closest('tr')?.querySelector('td') === el.closest('td'),
    );
    expect(firstInRow).toBe(true);
    await expect(
        dialog.getByRole('button', { name: /^dinsdag 15 juni 2027/ }),
    ).toHaveAttribute('aria-disabled', 'true');
});

test('table: sort buttons, aria-sort and the announcement (AC3)', async ({
    page,
}) => {
    await openDesign(page);
    const table = page.getByRole('table', { name: /Flights \(customer/ });
    const header = table.getByRole('columnheader', { name: /Seats/ });
    await expect(header).toHaveAttribute('aria-sort', 'none');
    await header.getByRole('button').click();
    await expect(header).toHaveAttribute('aria-sort', 'ascending');
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Gesorteerd op Seats, oplopend' }),
    ).toBeAttached();
    await header.getByRole('button').press('Enter');
    await expect(header).toHaveAttribute('aria-sort', 'descending');
});

test('table: staff table scrolls in a focusable named region (AC3)', async ({
    page,
}) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await openDesign(page);
    const region = page.getByRole('region', { name: /Bookings \(staff table/ });
    await expect(region).toHaveAttribute('tabindex', '0');
    await region.focus();
    await expect(region).toBeFocused();
});

test('table: stacks at 320 px and every cell keeps its header text (AC3)', async ({
    page,
}) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await openDesign(page);
    const result = await page.evaluate(() => {
        const table = document.querySelector('table');
        const cell = table?.querySelector('tbody td');
        const label = cell?.querySelector('[aria-hidden="true"]');
        const thead = table?.querySelector('thead');
        const headerBox = thead?.getBoundingClientRect();

        return {
            cellDisplay:
                cell === null || cell === undefined
                    ? ''
                    : getComputedStyle(cell).display,
            label: label?.textContent ?? '',
            labelVisible:
                label instanceof HTMLElement && label.offsetParent !== null,
            theadClipped: (headerBox?.width ?? 99) <= 1,
            scrolls:
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        };
    });
    expect(result.cellDisplay).toBe('flex');
    expect(result.label.length).toBeGreaterThan(0);
    expect(result.labelVisible).toBe(true);
    expect(result.theadClipped).toBe(true);
    expect(result.scrolls).toBe(false);
    // The accessible table structure survives display:block (explicit roles).
    await expect(
        page
            .getByRole('table', { name: /Flights \(customer/ })
            .getByRole('cell')
            .first(),
    ).toBeAttached();
});

test('table: empty state, reset filters, loading (AC4)', async ({ page }) => {
    await openDesign(page);
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Geen resultaten gevonden' }),
    ).toBeAttached();
    await expect(
        page.getByText('There are no flights for these filters.'),
    ).toBeVisible();
    await expect(
        page.getByRole('status').filter({ hasText: 'Loading flights…' }),
    ).toBeAttached();
    await expect(page.locator('[aria-busy="true"]')).toHaveCount(1);
    await page.getByRole('button', { name: 'Filters wissen' }).click();
    await expect(
        page.getByText('There are no flights for these filters.'),
    ).toHaveCount(0);
});

test('dialog: focus, trap, Escape, focus return, inert background (AC5)', async ({
    page,
}) => {
    await openDesign(page);
    const opener = page.getByRole('button', { name: 'Cancel booking' }).first();
    await opener.click();
    const dialog = page.getByRole('alertdialog', {
        name: 'Cancel this booking?',
    });
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('€');
    await expect(dialog).toContainText('21% VAT');
    await expect(page.getByRole('button', { name: 'Terug' })).toBeFocused();
    for (let i = 0; i < 6; i += 1) {
        await page.keyboard.press('Tab');
        const inside = await page.evaluate(
            () =>
                document.activeElement?.closest('[role="alertdialog"]') !==
                null,
        );
        expect(inside).toBe(true);
    }
    const bodyScrollLocked = await page.evaluate(
        () => getComputedStyle(document.documentElement).overflow === 'hidden',
    );
    expect(bodyScrollLocked).toBe(true);
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(opener).toBeFocused();
});

test('dialog: long content scrolls inside, actions stay in view at 400 % zoom with text spacing (AC5)', async ({
    page,
}) => {
    await page.setViewportSize({ width: 320, height: 256 });
    await openDesign(page);
    await applyTextSpacing(page);
    await page.getByRole('button', { name: 'Read the terms' }).click();
    const dialog = page.getByRole('dialog', { name: 'Terms of travel' });
    await expect(dialog).toBeVisible();
    const box = await page.locator('[role="dialog"]').evaluate((el) => {
        const rect =
            el.closest('[class*="max-h"]')?.getBoundingClientRect() ??
            el.getBoundingClientRect();

        return {
            top: rect.top,
            bottom: rect.bottom,
            viewport: window.innerHeight,
        };
    });
    expect(box.top).toBeGreaterThanOrEqual(0);
    expect(box.bottom).toBeLessThanOrEqual(box.viewport);
    // The body is a named, focusable scroll region; the title and the close button stay visible.
    const body = dialog.getByRole('region', { name: 'Terms of travel' });
    await expect(body).toHaveAttribute('tabindex', '0');
    await expect(
        dialog.getByRole('button', { name: 'Sluiten' }),
    ).toBeInViewport();
    await expect(
        dialog.getByRole('heading', { name: 'Terms of travel' }),
    ).toBeInViewport();
    const noPageScroll = await page.evaluate(
        () =>
            document.documentElement.scrollWidth <=
            document.documentElement.clientWidth,
    );
    expect(noPageScroll).toBe(true);
});

test('dialog: confirm dialog actions are reachable at 400 % zoom with text spacing (AC5)', async ({
    page,
}) => {
    await page.setViewportSize({ width: 320, height: 256 });
    await openDesign(page);
    await applyTextSpacing(page);
    await page.getByRole('button', { name: 'Cancel booking' }).first().click();
    const dialog = page.getByRole('alertdialog');
    await expect(
        dialog.getByRole('button', { name: 'Terug' }),
    ).toBeInViewport();
    await expect(
        dialog.getByRole('button', { name: 'Cancel booking' }),
    ).toBeInViewport({
        ratio: 0.9,
    });
});

test('toast: roles, never takes focus, keyboard dismissal, F6 (AC6, AC11)', async ({
    page,
}) => {
    await openDesign(page);
    const status = page.locator('[data-toast-region] output');
    const alertRegion = page.locator('[data-toast-region] [role="alert"]');
    await expect(status).toBeAttached();
    await expect(alertRegion).toBeAttached();
    const trigger = page.getByRole('button', {
        name: 'Show confirmation',
        exact: true,
    });
    await trigger.focus();
    await trigger.press('Enter');
    await expect(status).toContainText('Saved');
    await expect(trigger).toBeFocused();
    await page.getByRole('button', { name: 'Show error', exact: true }).click();
    await expect(alertRegion).toContainText('Not saved');
    expect(await page.locator('[role="alertdialog"][data-toast]').count()).toBe(
        0,
    );
    expect(await page.locator('[data-toast][role]').count()).toBe(0);

    await page.keyboard.press('F6');
    const inRegion = await page.evaluate(
        () => document.activeElement?.closest('[data-toast-region]') !== null,
    );
    expect(inRegion).toBe(true);
    await page.keyboard.press('Tab');
    await page.keyboard.press('Escape');
    await expect(page.locator('[data-toast]')).toHaveCount(1);
});

test('toast: a confirmation stays at least 8 s (AC6)', async ({ page }) => {
    await openDesign(page);
    await page
        .getByRole('button', { name: 'Show confirmation', exact: true })
        .click();
    const toast = page.locator('[data-toast]', { hasText: 'Saved' });
    await expect(toast).toBeVisible();
    await page.waitForTimeout(7000);
    await expect(toast).toBeVisible();
});

test('toast does not cover a focused control (2.4.11)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 600 });
    await openDesign(page);
    await page.getByRole('button', { name: 'Show error', exact: true }).click();
    await page
        .getByRole('button', { name: 'Show confirmation', exact: true })
        .click();
    await expect(page.locator('[data-toast]')).toHaveCount(2);
    const last = page.getByRole('link', { name: 'Vorige' }).last();
    await last.focus();
    const covered = await last.evaluate((el) => {
        const rect = el.getBoundingClientRect();
        const points = [
            [rect.left + 1, rect.top + 1],
            [rect.right - 1, rect.top + 1],
            [rect.left + 1, rect.bottom - 1],
            [rect.right - 1, rect.bottom - 1],
        ] as const;

        return points.some(([x, y]) => {
            const top = document.elementFromPoint(x, y);

            return top !== null && top.closest('[data-toast-region]') !== null;
        });
    });
    expect(covered).toBe(false);
});

test('pagination: full at 1280 px, compact text at 320 px (AC7)', async ({
    page,
}) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await openDesign(page);
    const nav = page.getByRole('navigation', { name: 'Paginering' }).first();
    await expect(nav.getByRole('link', { name: 'Pagina 3' })).toBeVisible();
    await expect(nav.getByText('Pagina 2 van 9')).toBeHidden();
    await expect(nav.getByRole('link', { name: 'Pagina 2' })).toHaveAttribute(
        'aria-current',
        'page',
    );
    await page.setViewportSize({ width: 320, height: 900 });
    await expect(nav.getByRole('link', { name: 'Pagina 3' })).toBeHidden();
    await expect(nav.getByText('Pagina 2 van 9')).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Vorige' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Volgende' })).toBeVisible();
});

test('forced colours: selected day, today, current page and highlighted option differ without fill (spec 4.15)', async ({
    page,
}) => {
    await page.emulateMedia({ forcedColors: 'active' });
    await openDesign(page);
    const current = page
        .getByRole('navigation', { name: 'Paginering' })
        .first()
        .getByRole('link', { name: 'Pagina 2' });
    const currentStyle = await current.evaluate((el) => {
        const style = getComputedStyle(el);

        return {
            underline: style.textDecorationLine,
            border: style.borderTopColor,
        };
    });
    expect(currentStyle.underline).toContain('underline');

    await page
        .getByRole('textbox', { name: 'Departure date' })
        .fill('12-06-2027');
    await page.getByRole('button', { name: 'Kalender openen' }).first().click();
    const selected = page
        .getByRole('dialog', { name: 'Kalender' })
        .getByRole('button', { name: /^zaterdag 12 juni 2027/ });
    const selectedStyle = await selected.evaluate((el) => {
        const style = getComputedStyle(el);

        return {
            underline: style.textDecorationLine,
            bottom: style.borderBottomWidth,
        };
    });
    expect(selectedStyle.underline).toContain('underline');
    expect(selectedStyle.bottom).toBe('4px');
    await page.keyboard.press('Escape');

    await page.getByRole('combobox', { name: 'Departure airport' }).fill('m');
    await page.keyboard.press('ArrowDown');
    const option = page.getByRole('option').first();
    const optionStyle = await option.evaluate((el) => {
        const style = getComputedStyle(el);

        return { bg: style.backgroundColor, color: style.color };
    });
    expect(optionStyle.bg).not.toBe(optionStyle.color);
});

test('reduced motion: nothing in the new components animates (spec 4.1)', async ({
    page,
}) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await openDesign(page);
    await page
        .getByRole('button', { name: 'Show confirmation', exact: true })
        .click();
    const animating = await page.evaluate(
        () =>
            document
                .getAnimations()
                .filter(
                    (animation) =>
                        Number(
                            animation.effect?.getComputedTiming().duration ?? 0,
                        ) > 1,
                )
                .map((animation) => animation.constructor.name).length,
    );
    expect(animating).toBe(0);
});

test('reflow at 320 px with text spacing: new sections do not scroll the page or clip text (AC8)', async ({
    page,
}) => {
    await page.setViewportSize({ width: 320, height: 256 });
    await openDesign(page);
    await applyTextSpacing(page);
    const result = await page.evaluate(() => ({
        overflow:
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
        clipped: [...document.querySelectorAll<HTMLElement>('main *')]
            .filter(
                (el) =>
                    el.offsetParent !== null &&
                    el.getBoundingClientRect().width > 1 &&
                    !el.closest('.sr-only') &&
                    !el.closest('[role="region"][tabindex="0"]') &&
                    el.scrollWidth > el.clientWidth + 1 &&
                    ['hidden', 'clip'].includes(getComputedStyle(el).overflowX),
            )
            .map((el) => el.tagName + '.' + el.className)
            .slice(0, 5),
    }));
    expect(result.overflow).toBe(false);
    expect(result.clipped).toEqual([]);
});
