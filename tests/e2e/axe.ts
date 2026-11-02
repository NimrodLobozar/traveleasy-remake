import AxeBuilder from '@axe-core/playwright';
import { test } from '@playwright/test';
import type { Page } from '@playwright/test';
import {
    formatResultBlocks,
    formatViolations,
    WCAG_AA_TAGS,
} from '../../resources/js/test/axe-config';
export interface A11yCheckOptions {
    /**
     * Second pass for shell pages (TE-016 AC9): axe's best-practice rules (landmarks, heading
     * order, skip link, region, ...) plus `label-content-name-mismatch`. No rule is disabled.
     */
    bestPractice?: boolean;
}

/** Name of the attachment that holds axe's incomplete ("needs review") results. */
export const NEEDS_REVIEW_ATTACHMENT = 'a11y-needs-review.txt';

/**
 * Runs axe (WCAG 2.0/2.1/2.2 A + AA, the tags shared with the component tests) on the current
 * page and throws on any violation. There is no allow-list (TE-016 AC7): fix the page. Per
 * violation the error lists the rule id, impact, help URL and, per node, the target selectors,
 * axe's failure summary and a shortened html snippet.
 *
 * Incomplete results (axe couldn't decide, e.g. contrast over a background image) don't fail the
 * test; they are attached to it as `a11y-needs-review.txt` (rule id, page URL, selectors, axe's
 * summary) so they show up in the HTML report for manual review.
 */
export async function expectNoA11yViolations(
    page: Page,
    { bestPractice = false }: A11yCheckOptions = {},
): Promise<void> {
    const url = page.url();
    const path = new URL(url).pathname;

    // withTags and withRules each replace the other's selection, so the best-practice pass runs
    // axe twice (the tags, then the one experimental rule) and merges the results.
    const runs = bestPractice
        ? [
              await new AxeBuilder({ page })
                  .withTags(['best-practice'])
                  .analyze(),
              await new AxeBuilder({ page })
                  .withRules(['label-content-name-mismatch'])
                  .analyze(),
          ]
        : [
              await new AxeBuilder({ page })
                  .withTags([...WCAG_AA_TAGS])
                  .analyze(),
          ];
    const results = {
        incomplete: runs.flatMap((run) => run.incomplete),
        violations: runs.flatMap((run) => run.violations),
    };

    if (results.incomplete.length > 0) {
        await test.info().attach(NEEDS_REVIEW_ATTACHMENT, {
            contentType: 'text/plain',
            body: [
                `${String(results.incomplete.length)} result(s) axe could not decide on ${url}; review them by hand:`,
                ...formatResultBlocks(results.incomplete),
            ].join('\n\n'),
        });
    }

    if (results.violations.length > 0) {
        throw new Error(formatViolations(results.violations, path));
    }
}
