import type { Result } from 'axe-core';

/**
 * Shared axe-core settings for component tests (Vitest, resources/js/test/axe.ts) and browser
 * tests (Playwright, tests/e2e/axe.ts), so both check against the same WCAG rule set.
 *
 * WCAG 2.0, 2.1 and 2.2 at levels A and AA (WCAG 2.2 added no new level-A rule to axe, so there is
 * no `wcag22a` tag). Best-practice and AAA rules are not part of the pass/fail verdict.
 */
export const WCAG_AA_TAGS = [
    'wcag2a',
    'wcag2aa',
    'wcag21a',
    'wcag21aa',
    'wcag22aa',
] as const;

/** The parts of an axe violation the report needs (axe-core and @axe-core/playwright share this shape). */
export type A11yViolation = Pick<
    Result,
    'id' | 'impact' | 'help' | 'helpUrl'
> & {
    nodes: { target: Result['nodes'][number]['target'] }[];
};

/**
 * One block per violation: rule id, impact, help text and URL, and every target selector.
 * Used as the failure message, so a failing run says exactly what to fix and where.
 */
export function formatViolations(
    violations: readonly A11yViolation[],
    context?: string,
): string {
    const header = `${String(violations.length)} accessibility violation(s)${
        context === undefined ? '' : ` on ${context}`
    }:`;
    const blocks = violations.map((violation) => {
        const targets = violation.nodes
            .map((node) => `    - ${node.target.map(String).join(' >> ')}`)
            .join('\n');

        return [
            `  [${violation.impact ?? 'unknown'}] ${violation.id}: ${violation.help}`,
            `    help: ${violation.helpUrl}`,
            `    targets:`,
            targets,
        ].join('\n');
    });

    return [header, ...blocks].join('\n\n');
}
