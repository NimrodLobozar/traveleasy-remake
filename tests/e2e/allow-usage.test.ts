import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';
import { describe, expect, it } from 'vite-plus/test';
import { placeholderAllowList } from './a11y-allow-list';

// Static checks on the browser-test code (TE-016 AC7, AC10; earlier TE-005 AC8). Runs in Vitest
// (`npm run test`, project "e2e-rules"), not in Playwright: it needs no browser and no app.
// The axe allow-list ended in TE-016: the helper has no `allow` option any more, and this scan
// makes sure nobody brings one back.

const e2eDir = fileURLToPath(new URL('.', import.meta.url));
const SCANNED = /\.[cm]?[jt]sx?$/;

/**
 * Where a file brings back an axe allow option:
 * - any property named `allow`: `allow: x`, `'allow': x`, `['allow']: x`, a template-literal key,
 *   a shorthand `{ allow }`, or a computed key that isn't a plain literal (it could be built);
 * - `x.allow` and `x['allow']`;
 * - any expectNoA11yViolations(page, options) call whose options aren't an object literal made
 *   of plain `bestPractice` properties.
 */
export function findAllowUsages(fileName: string, source: string): string[] {
    const file = ts.createSourceFile(
        fileName,
        source,
        ts.ScriptTarget.Latest,
        true,
    );
    const found: string[] = [];
    const report = (node: ts.Node, what: string) => {
        const { line } = file.getLineAndCharacterOfPosition(node.getStart());
        found.push(`${fileName}:${String(line + 1)} ${what}`);
    };
    const isLiteral = (node: ts.Node): node is ts.StringLiteralLike =>
        ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node);

    const visit = (node: ts.Node): void => {
        if (ts.isPropertyAssignment(node)) {
            const { name } = node;
            if (
                (ts.isIdentifier(name) || ts.isStringLiteral(name)) &&
                name.text === 'allow'
            ) {
                report(node, `allow key: ${name.getText(file)}`);
            } else if (ts.isComputedPropertyName(name)) {
                const inner = name.expression;
                if (!isLiteral(inner) || inner.text === 'allow') {
                    report(node, `computed key: ${name.getText(file)}`);
                }
            }
        }
        if (
            ts.isShorthandPropertyAssignment(node) &&
            node.name.text === 'allow'
        ) {
            report(node, '{ allow } shorthand');
        }
        if (ts.isPropertyAccessExpression(node) && node.name.text === 'allow') {
            report(node, '.allow access');
        }
        if (
            ts.isElementAccessExpression(node) &&
            isLiteral(node.argumentExpression) &&
            node.argumentExpression.text === 'allow'
        ) {
            report(node, "['allow'] access");
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
                            ts.isPropertyAssignment(property) &&
                            ts.isIdentifier(property.name) &&
                            property.name.text === 'bestPractice',
                    )
                )
            ) {
                report(
                    node,
                    `options must be a literal with only bestPractice: ${options.getText(file)}`,
                );
            }
        }
        ts.forEachChild(node, visit);
    };
    visit(file);

    return found;
}

function sourceFiles(dir: string): string[] {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);
        if (entry.isDirectory()) {
            return sourceFiles(path);
        }
        return SCANNED.test(entry.name) ? [path] : [];
    });
}

/** Line numbers (as "name:line") of the findings, one per finding. */
const lines = (found: string[]) => found.map((entry) => entry.split(' ')[0]);

describe('no axe allow option under tests/e2e (TE-016 AC7, AC10)', () => {
    it('keeps the allow-list empty', () => {
        expect(placeholderAllowList).toHaveLength(0);
    });

    it('finds no allow option in any .ts, .tsx, .js, .mjs or .cjs file', () => {
        const usages = sourceFiles(e2eDir).flatMap((path) =>
            findAllowUsages(relative(e2eDir, path), readFileSync(path, 'utf8')),
        );

        expect(usages).toEqual([]);
    });

    it('scans the file types that matter', () => {
        for (const name of ['a.ts', 'a.tsx', 'a.js', 'a.mjs', 'a.cjs']) {
            expect(SCANNED.test(name)).toBe(true);
        }
        expect(SCANNED.test('a.json')).toBe(false);
    });

    it('accepts the plain calls', () => {
        const source = [
            'await expectNoA11yViolations(page);',
            'await expectNoA11yViolations(page, { bestPractice: true });',
        ].join('\n');

        expect(findAllowUsages('ok.spec.ts', source)).toEqual([]);
    });

    it('detects each way of passing allow (fixtures)', () => {
        const cases: [string, string][] = [
            ['plain key', 'f(page, { allow: list });'],
            ['string key', "f(page, { 'allow': list });"],
            ['computed literal key', "f(page, { ['allow']: list });"],
            ['template key', 'f(page, { [`allow`]: list });'],
            ['built key', "f(page, { ['al' + 'low']: list });"],
            ['variable key', 'f(page, { [key]: list });'],
            ['shorthand', 'f(page, { allow });'],
            ['property read', 'const a = options.allow;'],
            ['element read', "const a = options['allow'];"],
        ];
        for (const [name, code] of cases) {
            const found = findAllowUsages('probe.spec.ts', code);
            expect(found.length, name).toBeGreaterThan(0);
            expect(lines(found)[0], name).toBe('probe.spec.ts:1');
        }
    });

    it('detects opaque or extra options on the helper call', () => {
        const source = [
            'await expectNoA11yViolations(page, options);',
            'await expectNoA11yViolations(page, { ...options });',
            'await expectNoA11yViolations(page, { other: true });',
        ].join('\n');

        expect(lines(findAllowUsages('probe.spec.ts', source))).toEqual([
            'probe.spec.ts:1',
            'probe.spec.ts:2',
            'probe.spec.ts:3',
        ]);
    });

    it('parses plain JavaScript files too (.js, .mjs)', () => {
        for (const name of ['probe.js', 'probe.mjs']) {
            const found = findAllowUsages(
                name,
                "await expectNoA11yViolations(page, { ['allow']: list });",
            );

            expect(found.length, name).toBeGreaterThan(0);
        }
    });
});
