import AxeBuilder from '@axe-core/playwright';
import { test } from '@playwright/test';
import type { Page } from '@playwright/test';
import {
    formatResultBlocks,
    formatViolations,
    WCAG_AA_TAGS,
} from '../../resources/js/test/axe-config';
import { assertValidAllowEntries } from './a11y-allow';
import type { A11yAllowEntry } from './a11y-allow';

export type { A11yAllowEntry } from './a11y-allow';

export interface A11yCheckOptions {
    /**
     * Allowed violations; entries for other pages are ignored. Outside axe-helper.spec.ts this
     * must be `placeholderAllowList` (enforced by allow-usage.test.ts).
     */
    allow?: readonly A11yAllowEntry[];
}

/** Name of the attachment that holds axe's incomplete ("needs review") results. */
export const NEEDS_REVIEW_ATTACHMENT = 'a11y-needs-review.txt';

/**
 * Runs axe (WCAG 2.0/2.1/2.2 A + AA, the tags shared with the component tests) on the current
 * page and throws when it finds a violation that isn't on the allow-list. Per violation the
 * error lists the rule id, impact, help URL and, per node, the target selectors, axe's failure
 * summary and a shortened html snippet.
 *
 * It also throws on an invalid allow entry (see a11y-allow.ts), on duplicate entries, and when
 * an allow entry for this page no longer matches a violation, so the list only shrinks.
 *
 * Incomplete results (axe couldn't decide, e.g. contrast over a background image) don't fail the
 * test; they are attached to it as `a11y-needs-review.txt` (rule id, page URL, selectors, axe's
 * summary) so they show up in the HTML report for manual review.
 */
export async function expectNoA11yViolations(
    page: Page,
    { allow = [] }: A11yCheckOptions = {},
): Promise<void> {
    assertValidAllowEntries(allow);
    const url = page.url();
    const path = new URL(url).pathname;
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

    if (results.incomplete.length > 0) {
        await test.info().attach(NEEDS_REVIEW_ATTACHMENT, {
            contentType: 'text/plain',
            body: [
                `${String(results.incomplete.length)} result(s) axe could not decide on ${url}; review them by hand:`,
                ...formatResultBlocks(results.incomplete),
            ].join('\n\n'),
        });
    }

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
