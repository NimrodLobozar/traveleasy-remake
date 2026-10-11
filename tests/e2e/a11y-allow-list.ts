import type { A11yAllowEntry } from './a11y-allow';

/**
 * Axe violations that are allowed for now, one entry per rule id AND page (TE-005 AC8). The
 * ONLY place where violations may be allowed (allow-usage.test.ts enforces it).
 *
 * Only starter-kit placeholder pages may be listed; they are replaced by the App shell and
 * design-system epics, which must end with this list empty. a11y-allow-list.spec.ts visits every
 * page listed here and fails when the page is gone or no longer has the violation.
 */
export const placeholderAllowList: readonly A11yAllowEntry[] = [
    {
        // Kit welcome page: grey text on white (h1, intro, list items, links) is below 4.5:1.
        page: '/',
        rule: 'color-contrast',
        reason: 'starter-kit placeholder, remove in App shell',
    },
];
