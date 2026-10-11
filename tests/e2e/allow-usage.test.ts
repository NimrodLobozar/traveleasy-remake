import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';
import { describe, expect, it } from 'vite-plus/test';
import { assertValidAllowEntries } from './a11y-allow';
import type { A11yAllowEntry } from './a11y-allow';
import { placeholderAllowList } from './a11y-allow-list';

// Static checks on the browser-test code (TE-005 AC8a, AC8c). Runs in Vitest (`npm run test`,
// project "e2e-rules"), not in Playwright: it needs no browser and no app.

const e2eDir = fileURLToPath(new URL('.', import.meta.url));
const CENTRAL_LIST = 'placeholderAllowList';
// The one spec that proves the allow mechanism with its own entries.
const EXEMPT_FILES = new Set(['axe-helper.spec.ts']);

/**
 * Where a file allows axe violations other than through placeholderAllowList:
 * - any `allow: <x>` property (or `{ allow }` shorthand) whose value isn't `placeholderAllowList`;
 * - any expectNoA11yViolations(page, options) call whose options aren't an object literal
 *   (a variable or spread could carry inline entries).
 */
export function findInlineAllowUsages(
    fileName: string,
    source: string,
): string[] {
    const file = ts.createSourceFile(
        fileName,
        source,
        ts.ScriptTarget.Latest,
        true,
    );
    const found: string[] = [];
    const where = (node: ts.Node) => {
        const { line } = file.getLineAndCharacterOfPosition(node.getStart());
        return `${fileName}:${String(line + 1)}`;
    };
    const isAllowName = (name: ts.PropertyName) =>
        (ts.isIdentifier(name) || ts.isStringLiteral(name)) &&
        name.text === 'allow';

    const visit = (node: ts.Node): void => {
        if (
            ts.isPropertyAssignment(node) &&
            isAllowName(node.name) &&
            !(
                ts.isIdentifier(node.initializer) &&
                node.initializer.text === CENTRAL_LIST
            )
        ) {
            found.push(
                `${where(node)} allow: ${node.initializer.getText(file)}`,
            );
        }
        if (
            ts.isShorthandPropertyAssignment(node) &&
            node.name.text === 'allow'
        ) {
            found.push(`${where(node)} { allow } shorthand`);
        }
        if (
            ts.isCallExpression(node) &&
            ts.isIdentifier(node.expression) &&
            node.expression.text === 'expectNoA11yViolations'
        ) {
            const options = node.arguments[1];
            if (
                options !== undefined &&
                !(
                    ts.isObjectLiteralExpression(options) &&
                    options.properties.every(
                        (property) =>
                            ts.isPropertyAssignment(property) ||
                            ts.isShorthandPropertyAssignment(property),
                    )
                )
            ) {
                found.push(
                    `${where(node)} options must be an object literal: ${options.getText(file)}`,
                );
            }
        }
        ts.forEachChild(node, visit);
    };
    visit(file);

    return found;
}

function e2eSourceFiles(dir: string): string[] {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);
        if (entry.isDirectory()) {
            return e2eSourceFiles(path);
        }
        return /\.[cm]?tsx?$/.test(entry.name) ? [path] : [];
    });
}

describe('axe allow-list usage under tests/e2e (AC8a)', () => {
    it('allows violations only through placeholderAllowList', () => {
        const usages = e2eSourceFiles(e2eDir)
            .filter((path) => !EXEMPT_FILES.has(relative(e2eDir, path)))
            .flatMap((path) =>
                findInlineAllowUsages(
                    relative(e2eDir, path),
                    readFileSync(path, 'utf8'),
                ),
            );

        expect(usages).toEqual([]);
    });

    it('detects inline entries, other lists, shorthands and opaque options', () => {
        const source = [
            `await expectNoA11yViolations(page, { allow: placeholderAllowList });`,
            `await expectNoA11yViolations(page);`,
            `await expectNoA11yViolations(page, { allow: [{ page: '/', rule: 'x', reason: 'r' }] });`,
            `await expectNoA11yViolations(page, { allow: otherList });`,
            `await expectNoA11yViolations(page, { allow });`,
            `await expectNoA11yViolations(page, options);`,
            `await expectNoA11yViolations(page, { ...options });`,
        ].join('\n');

        expect(findInlineAllowUsages('probe.spec.ts', source)).toEqual([
            "probe.spec.ts:3 allow: [{ page: '/', rule: 'x', reason: 'r' }]",
            'probe.spec.ts:4 allow: otherList',
            'probe.spec.ts:5 { allow } shorthand',
            'probe.spec.ts:6 options must be an object literal: options',
            'probe.spec.ts:7 options must be an object literal: { ...options }',
        ]);
    });
});

describe('allow entry reasons (AC8c)', () => {
    it('accepts the central list', () => {
        expect(() => {
            assertValidAllowEntries(placeholderAllowList);
        }).not.toThrow();
    });

    it('rejects an empty, blank or unknown epic name', () => {
        const entry = (reason: string) =>
            ({
                page: '/',
                rule: 'color-contrast',
                reason,
            }) as unknown as A11yAllowEntry;

        for (const reason of [
            'starter-kit placeholder, remove in ',
            'starter-kit placeholder, remove in    ',
            'starter-kit placeholder, remove in someday',
            'remove later',
        ]) {
            expect(() => {
                assertValidAllowEntries([entry(reason)]);
            }).toThrow(/Invalid a11y allow entry/);
        }
    });

    it('rejects a blank epic name at the type level too', () => {
        const blank: A11yAllowEntry = {
            page: '/',
            rule: 'color-contrast',
            // @ts-expect-error -- the epic must be one of REMOVAL_EPICS, not blank
            reason: 'starter-kit placeholder, remove in  ',
        };

        expect(() => {
            assertValidAllowEntries([blank]);
        }).toThrow(/Invalid a11y allow entry/);
    });
});
