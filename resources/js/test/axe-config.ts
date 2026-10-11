import type { NodeResult, Result } from 'axe-core';

/**
 * Shared axe-core settings and report format for component tests (Vitest,
 * resources/js/test/axe.ts) and browser tests (Playwright, tests/e2e/axe.ts), so both check
 * against the same WCAG rule set and print the same details.
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

/** Longest html snippet printed per node; longer markup is cut with an ellipsis. */
export const HTML_SNIPPET_MAX_LENGTH = 200;

type A11yNode = Pick<NodeResult, 'target' | 'html' | 'failureSummary'> &
    Partial<Pick<NodeResult, 'any' | 'all' | 'none'>>;

/**
 * The parts of an axe result the report needs (axe-core and @axe-core/playwright share this
 * shape). Used for violations and for incomplete ("needs review") results.
 */
export type A11yResult = Pick<Result, 'id' | 'impact' | 'help' | 'helpUrl'> & {
    nodes: A11yNode[];
};

/** Markup on one line, cut to HTML_SNIPPET_MAX_LENGTH characters. */
export function htmlSnippet(html: string): string {
    const oneLine = html.replace(/\s+/g, ' ').trim();

    return oneLine.length > HTML_SNIPPET_MAX_LENGTH
        ? `${oneLine.slice(0, HTML_SNIPPET_MAX_LENGTH - 1)}…`
        : oneLine;
}

/**
 * axe's own summary of what failed on a node. axe fills `failureSummary` for violations; for
 * results without it, the check messages are joined instead.
 */
function summaryOf(node: A11yNode): string {
    const summary =
        node.failureSummary ??
        [...(node.any ?? []), ...(node.all ?? []), ...(node.none ?? [])]
            .map((check) => check.message)
            .filter((message) => message !== '')
            .join('; ');

    return summary.replace(/\s*\n\s*/g, ' | ').trim() || '(none)';
}

/** One block per result: rule id, impact, help text and URL; per node the target, axe's summary and html. */
export function formatResultBlocks(results: readonly A11yResult[]): string[] {
    return results.map((result) => {
        const nodes = result.nodes.map((node) =>
            [
                `    - ${node.target.map(String).join(' >> ')}`,
                `      summary: ${summaryOf(node)}`,
                `      html: ${htmlSnippet(node.html)}`,
            ].join('\n'),
        );

        return [
            `  [${result.impact ?? 'unknown'}] ${result.id}: ${result.help}`,
            `    help: ${result.helpUrl}`,
            `    targets:`,
            ...nodes,
        ].join('\n');
    });
}

/**
 * The failure message for violations. Used by both helpers, so a failing run says exactly what
 * to fix and where.
 */
export function formatViolations(
    violations: readonly A11yResult[],
    context?: string,
): string {
    const header = `${String(violations.length)} accessibility violation(s)${
        context === undefined ? '' : ` on ${context}`
    }:`;

    return [header, ...formatResultBlocks(violations)].join('\n\n');
}
