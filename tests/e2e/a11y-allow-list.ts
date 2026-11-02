// The old axe allow-list (TE-005) ended empty in TE-016, and the helper no longer has an "allow"
// option. allow-usage.test.ts asserts this stays empty and that nothing under tests/e2e brings
// the option back.
export const placeholderAllowList: readonly string[] = [];
