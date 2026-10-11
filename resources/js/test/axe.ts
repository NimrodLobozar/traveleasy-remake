import axe from 'axe-core';
import { expect } from 'vite-plus/test';
import {
    formatResultBlocks,
    formatViolations,
    WCAG_AA_TAGS,
} from './axe-config';

/**
 * Component-level axe matcher (TE-005 AC3), registered in setup.ts:
 *
 *     const { container } = render(<MyComponent />);
 *     await expect(container).toHaveNoA11yViolations();
 *
 * Runs axe-core on the element with the shared WCAG A/AA tags, in jsdom.
 *
 * What this matcher does NOT check. jsdom has no layout engine and Vitest loads no CSS, so:
 * - These rules are switched off (JSDOM_DISABLED_RULES), because in jsdom they give false passes
 *   or never apply: `color-contrast` (no computed colours), `link-in-text-block` (colour
 *   difference between link and text), `target-size` (every box is 0×0, so it passes on targets
 *   of any size) and `scrollable-region-focusable` (nothing ever overflows).
 * - Anything that depends on rendering: whether an element is visible or hidden through CSS
 *   classes (Tailwind's `hidden`, `sr-only`, … are not applied, so such content counts as
 *   visible), size, position, overlap, reflow, zoom, text spacing, focus visibility and focus
 *   obscured by other content.
 * Those are covered by Playwright + axe in a real browser (tests/e2e), which the design-system
 * and App shell epics use for every component and page.
 *
 * Incomplete ("needs review") results are not dropped:
 * - For rules that can be judged without layout (JSDOM_FAIL_ON_INCOMPLETE) an incomplete result
 *   fails the matcher, like a violation. In jsdom, axe can't confirm `aria-hidden-focus` (it
 *   can't tell whether the focusable element is visible), so a focusable element inside
 *   `aria-hidden="true"` comes back incomplete; and `aria-valid-attr-value` is incomplete when an
 *   ID reference (aria-describedby, aria-controls, …) points at an element that doesn't exist.
 * - Every other incomplete result is listed by rule id: in the failure message when the matcher
 *   fails, otherwise as a console note, so it can be reviewed by hand.
 *
 * Built on axe-core directly instead of vitest-axe (last release 2022, pre-Vitest-4 type
 * augmentation, extra dependencies); axe-core is the same version @axe-core/playwright uses.
 */
export const JSDOM_DISABLED_RULES = [
    'color-contrast',
    'link-in-text-block',
    'target-size',
    'scrollable-region-focusable',
] as const;

export const JSDOM_FAIL_ON_INCOMPLETE = [
    'aria-hidden-focus',
    'aria-valid-attr-value',
] as const;

const failOnIncomplete = new Set<string>(JSDOM_FAIL_ON_INCOMPLETE);

async function toHaveNoA11yViolations(received: unknown) {
    if (!(received instanceof Element)) {
        throw new TypeError(
            'toHaveNoA11yViolations() expects a DOM Element, e.g. the `container` from render().',
        );
    }

    const results = await axe.run(received, {
        runOnly: { type: 'tag', values: [...WCAG_AA_TAGS] },
        rules: Object.fromEntries(
            JSDOM_DISABLED_RULES.map((rule) => [rule, { enabled: false }]),
        ),
    });

    const failingIncomplete = results.incomplete.filter((result) =>
        failOnIncomplete.has(result.id),
    );
    const otherIncomplete = results.incomplete
        .filter((result) => !failOnIncomplete.has(result.id))
        .map((result) => result.id);
    const pass =
        results.violations.length === 0 && failingIncomplete.length === 0;
    const reviewNote =
        otherIncomplete.length === 0
            ? ''
            : `Needs manual review (axe incomplete in jsdom): ${otherIncomplete.join(', ')}.`;

    if (pass && reviewNote !== '') {
        // The AC3 "console note": a passing test must still show what axe couldn't decide.
        // oxlint-disable-next-line no-console -- deliberate test-only note, see the comment above
        console.warn(reviewNote);
    }

    return {
        pass,
        message: () => {
            if (pass) {
                return 'Expected accessibility violations, but axe found none.';
            }
            const parts: string[] = [];
            if (results.violations.length > 0) {
                parts.push(formatViolations(results.violations));
            }
            if (failingIncomplete.length > 0) {
                parts.push(
                    [
                        `${String(failingIncomplete.length)} incomplete result(s) that fail in jsdom (axe could not confirm them as passing):`,
                        ...formatResultBlocks(failingIncomplete),
                    ].join('\n\n'),
                );
            }
            if (reviewNote !== '') {
                parts.push(reviewNote);
            }

            return parts.join('\n\n');
        },
    };
}

expect.extend({ toHaveNoA11yViolations });

declare module 'vitest' {
    // Type parameters must match Vitest's own `Assertion<T = any>` for declaration merging.
    // oxlint-disable-next-line typescript/no-explicit-any -- must equal Vitest's declaration
    interface Assertion<T = any> {
        toHaveNoA11yViolations: () => Promise<void>;
    }
}
