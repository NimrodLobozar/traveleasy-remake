import type { A11yAllowEntry } from './axe';

/**
 * Axe violations that are allowed for now, one entry per rule id AND page (TE-005 AC8).
 *
 * Only starter-kit placeholder pages may be listed; they are replaced by the App shell and
 * design-system epics, which must end with this list empty. The helper fails when an entry no
 * longer matches a violation, so fixed issues drop off.
 */
export const placeholderAllowList: readonly A11yAllowEntry[] = [
    {
        // Kit welcome page: grey text on white (h1, intro, list items, links) is below 4.5:1.
        page: '/',
        rule: 'color-contrast',
        reason: 'starter-kit placeholder, remove in M0 App shell epic',
    },
];
