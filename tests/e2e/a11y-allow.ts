/**
 * The rules for allowing axe violations in browser tests (TE-005 AC8). Kept free of Playwright
 * imports, so the Vitest checks in this folder can use it too.
 *
 * - One entry per rule id AND page; only starter-kit placeholder pages qualify.
 * - The reason names the epic that removes the entry, from REMOVAL_EPICS (a type and a runtime
 *   check, so an empty or made-up epic name is rejected).
 * - Entries live only in placeholderAllowList (a11y-allow-list.ts); allow-usage.test.ts fails
 *   on any other `allow:` value under tests/e2e (except axe-helper.spec.ts, which proves the
 *   mechanism).
 */

/** Epics that replace starter-kit placeholder pages (docs/plan.md, M0). */
export const REMOVAL_EPICS = ['App shell', 'Design system'] as const;

export type RemovalEpic = (typeof REMOVAL_EPICS)[number];

const REASON_PREFIX = 'starter-kit placeholder, remove in ';

export interface A11yAllowEntry {
    /** Path of the page, e.g. `/login`. */
    page: `/${string}`;
    /** One axe rule id. */
    rule: string;
    reason: `starter-kit placeholder, remove in ${RemovalEpic}`;
}

/**
 * Throws when an entry's reason doesn't name a known epic (also catches entries built around the
 * type, e.g. with a cast or from untyped data) or when a rule id or page is blank.
 */
export function assertValidAllowEntries(
    entries: readonly A11yAllowEntry[],
): void {
    const epics: readonly string[] = REMOVAL_EPICS;
    for (const entry of entries) {
        const reason: string = entry.reason;
        const epic = reason.startsWith(REASON_PREFIX)
            ? reason.slice(REASON_PREFIX.length)
            : '';
        if (!epics.includes(epic)) {
            throw new Error(
                `Invalid a11y allow entry (${entry.page} ${entry.rule}): the reason must be "${REASON_PREFIX}<epic>" with one of: ${epics.join(', ')}. Got "${reason}".`,
            );
        }
        if (entry.rule.trim() === '' || !entry.page.startsWith('/')) {
            throw new Error(
                `Invalid a11y allow entry: page "${entry.page}" and rule "${entry.rule}" must both be set.`,
            );
        }
    }
}
