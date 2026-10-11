import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import {
    formatViolations,
    WCAG_AA_TAGS,
} from '../../resources/js/test/axe-config';

/**
 * One allowed axe violation: exactly one rule id on exactly one page (path, e.g. `/login`).
 * Only starter-kit placeholder pages may be listed (TE-005 AC8), and the reason says which
 * epic removes the entry. The App shell epic must end with an empty list.
 */
export interface A11yAllowEntry {
    page: `/${string}`;
    rule: string;
    reason: `starter-kit placeholder, remove in ${string}`;
}

export interface A11yCheckOptions {
    /** Allowed violations; entries for other pages are ignored. */
    allow?: readonly A11yAllowEntry[];
}

/**
 * Runs axe (WCAG 2.0/2.1/2.2 A + AA, the tags shared with the component tests) on the current
 * page and throws when it finds a violation that isn't on the allow-list. The error lists each
 * violation's rule id, impact, help URL and target selectors.
 *
 * It also throws when an allow entry for this page no longer matches a violation, so fixed
 * issues can't stay allowed (the list only shrinks), and on duplicate entries.
 */
export async function expectNoA11yViolations(
    page: Page,
    { allow = [] }: A11yCheckOptions = {},
): Promise<void> {
    const path = new URL(page.url()).pathname;
    const allowedHere = allow.filter((entry) => entry.page === path);

    const keys = allowedHere.map((entry) => entry.rule);
    const duplicates = keys.filter(
        (rule, index) => keys.indexOf(rule) !== index,
    );
    if (duplicates.length > 0) {
        throw new Error(
            `Duplicate a11y allow entries for ${path}: ${duplicates.join(', ')}.`,
        );
    }

    const results = await new AxeBuilder({ page })
        .withTags([...WCAG_AA_TAGS])
        .analyze();

    const allowedRules = new Set(keys);
    const unexpected = results.violations.filter(
        (violation) => !allowedRules.has(violation.id),
    );
    const found = new Set(results.violations.map((violation) => violation.id));
    const stale = allowedHere.filter((entry) => !found.has(entry.rule));

    const problems: string[] = [];
    if (unexpected.length > 0) {
        problems.push(formatViolations(unexpected, path));
    }
    if (stale.length > 0) {
        problems.push(
            `Allow entries for ${path} that no longer match a violation (remove them): ${stale
                .map((entry) => entry.rule)
                .join(', ')}.`,
        );
    }
    if (problems.length > 0) {
        throw new Error(problems.join('\n\n'));
    }
}
