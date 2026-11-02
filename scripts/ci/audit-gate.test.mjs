import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
    classify,
    composerAdvisories,
    loadIgnores,
    npmAdvisories,
    run,
} from './audit-gate.mjs';

const now = new Date('2026-11-10T12:00:00Z');

const composerReport = {
    advisories: {
        'acme/lib': [
            {
                advisoryId: 'PKSA-1',
                cve: 'CVE-2026-1',
                title: 'bad',
                severity: 'high',
                sources: [{ name: 'GitHub', remoteId: 'GHSA-aaaa-bbbb-cccc' }],
            },
            {
                advisoryId: 'PKSA-2',
                title: 'meh',
                severity: 'medium',
                sources: [],
            },
        ],
    },
};

const npmReport = {
    vulnerabilities: {
        undici: {
            via: [
                {
                    source: 1,
                    name: 'undici',
                    url: 'https://github.com/advisories/GHSA-xxxx-yyyy-zzzz',
                    severity: 'high',
                    title: 'dos',
                },
            ],
        },
        vitest: { via: ['undici'] },
    },
};

const ignoreFile = (overrides = {}) => ({
    ignores: [
        {
            id: 'GHSA-aaaa-bbbb-cccc',
            tool: 'composer',
            package: 'acme/lib',
            reason: 'Dev only, not reachable in production.',
            reviewed: '2026-11-01',
            expires: '2026-12-01',
            ...overrides,
        },
    ],
});

const runWith = (tool, report, ignore) => {
    const dir = mkdtempSync(join(tmpdir(), 'audit-gate-'));
    writeFileSync(join(dir, 'report.json'), JSON.stringify(report));
    writeFileSync(join(dir, 'ignore.json'), JSON.stringify(ignore));
    return run(
        [
            '--tool',
            tool,
            '--report',
            join(dir, 'report.json'),
            '--ignore',
            join(dir, 'ignore.json'),
        ],
        now,
    );
};

describe('audit gate', () => {
    it('fails on a high advisory that is not ignored', () => {
        const result = runWith('composer', composerReport, { ignores: [] });
        expect(result.code).toBe(1);
        expect(result.lines.join('\n')).toContain('BLOCKING: HIGH acme/lib');
    });

    it('passes when the advisory is ignored by any of its IDs', () => {
        for (const id of ['PKSA-1', 'CVE-2026-1', 'GHSA-aaaa-bbbb-cccc']) {
            expect(
                runWith('composer', composerReport, ignoreFile({ id })).code,
            ).toBe(0);
        }
    });

    it('does not block medium and low advisories', () => {
        const report = {
            advisories: { 'a/b': [{ advisoryId: 'X', severity: 'low' }] },
        };
        expect(runWith('composer', report, { ignores: [] }).code).toBe(0);
    });

    it('treats a missing severity as blocking', () => {
        const report = { advisories: { 'a/b': [{ advisoryId: 'X' }] } };
        expect(runWith('composer', report, { ignores: [] }).code).toBe(1);
    });

    it('fails when an ignore entry has expired', () => {
        const result = runWith(
            'composer',
            composerReport,
            ignoreFile({ expires: '2026-11-05' }),
        );
        expect(result.code).toBe(1);
        expect(result.lines.join('\n')).toContain('expired on 2026-11-05');
    });

    it('rejects an ignore entry without a reason or review date', () => {
        expect(
            runWith('composer', composerReport, ignoreFile({ reason: '' }))
                .code,
        ).toBe(1);
        expect(
            runWith(
                'composer',
                composerReport,
                ignoreFile({ reviewed: 'yesterday' }),
            ).code,
        ).toBe(1);
    });

    it('reads npm audit output and matches the GHSA id', () => {
        expect(npmAdvisories(npmReport)).toHaveLength(1);
        expect(runWith('npm', npmReport, { ignores: [] }).code).toBe(1);
        expect(
            runWith(
                'npm',
                npmReport,
                ignoreFile({ id: 'GHSA-xxxx-yyyy-zzzz', tool: 'npm' }),
            ).code,
        ).toBe(0);
    });

    it('does not apply a composer ignore to an npm advisory', () => {
        const file = ignoreFile({
            id: 'GHSA-xxxx-yyyy-zzzz',
            tool: 'composer',
        });
        expect(runWith('npm', npmReport, file).code).toBe(1);
    });

    it('exits 2 on bad usage or unreadable input', () => {
        expect(run(['--tool', 'pip'], now).code).toBe(2);
        expect(
            run(['--tool', 'npm', '--report', '/nonexistent.json'], now).code,
        ).toBe(2);
    });

    it('exposes the parsing helpers', () => {
        expect(composerAdvisories(composerReport)[0].ids).toContain(
            'CVE-2026-1',
        );
        expect(loadIgnores(ignoreFile(), 'composer', now).active).toHaveLength(
            1,
        );
        expect(classify([], []).blocking).toEqual([]);
    });
});
