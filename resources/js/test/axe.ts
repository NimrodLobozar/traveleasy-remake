import axe from 'axe-core';
import { expect } from 'vite-plus/test';
import { formatViolations, WCAG_AA_TAGS } from './axe-config';

/**
 * Component-level axe matcher (TE-005 AC3), registered in setup.ts:
 *
 *     const { container } = render(<MyComponent />);
 *     await expect(container).toHaveNoA11yViolations();
 *
 * Runs axe-core on the element with the shared WCAG A/AA tags. jsdom has no layout or colours, so
 * rules that need them (color-contrast, target-size, …) come back "incomplete", not as passes:
 * those are covered by the Playwright + axe tests in tests/e2e, which run in a real browser.
 *
 * Built on axe-core directly instead of vitest-axe (last release 2022, pre-Vitest-4 type
 * augmentation, extra dependencies); axe-core is the same version @axe-core/playwright uses.
 */
async function toHaveNoA11yViolations(received: unknown) {
    if (!(received instanceof Element)) {
        throw new TypeError(
            'toHaveNoA11yViolations() expects a DOM Element, e.g. the `container` from render().',
        );
    }

    const results = await axe.run(received, {
        runOnly: { type: 'tag', values: [...WCAG_AA_TAGS] },
    });
    const pass = results.violations.length === 0;

    return {
        pass,
        message: () =>
            pass
                ? 'Expected accessibility violations, but axe found none.'
                : formatViolations(results.violations),
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
