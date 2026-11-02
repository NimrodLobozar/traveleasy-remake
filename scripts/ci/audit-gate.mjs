#!/usr/bin/env node
// Dependency audit gate (TE-013 AC10). Reads the JSON of `composer audit --format=json` or `npm audit --json`
// and fails when a high or critical advisory (or one without a severity) is not listed in the reviewed ignore file.
//
//   node scripts/ci/audit-gate.mjs --tool composer|npm --report <file.json> [--ignore .github/audit-ignore.json]
//
// Ignore file: { "ignores": [{ "id", "tool", "package", "reason", "reviewed": "YYYY-MM-DD", "expires": "YYYY-MM-DD" }] }.
// An entry matches an advisory by any of its IDs (GHSA, CVE, PKSA, npm advisory number). It stops counting after
// `expires`, so the triage has to be repeated. Medium and low advisories are listed, not blocking.
// Exit code: 0 = pass, 1 = blocking advisories, 2 = usage or invalid input.
import { readFileSync } from 'node:fs';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const BLOCKING = new Set(['high', 'critical', 'unknown']);

/**
 * @typedef {{ ids: string[], severity: string, pkg: string, title: string }} Advisory
 * @typedef {{ id: string, tool: string, package: string, reason: string, reviewed: string, expires: string }} Ignore
 */

/**
 * @param {unknown} value
 * @returns {Record<string, unknown>}
 */
const asRecord = (value) =>
    typeof value === 'object' && value !== null && !Array.isArray(value)
        ? /** @type {Record<string, unknown>} */ (value)
        : {};

/** @param {unknown} value */
const asString = (value) => (typeof value === 'string' ? value : '');

/**
 * @param {unknown} report parsed `composer audit --format=json`
 * @returns {Advisory[]}
 */
export function composerAdvisories(report) {
    /** @type {Advisory[]} */
    const found = [];
    const advisories = asRecord(asRecord(report).advisories);
    for (const [pkg, list] of Object.entries(advisories)) {
        for (const raw of Array.isArray(list) ? list : []) {
            const item = asRecord(raw);
            const sources = Array.isArray(item.sources) ? item.sources : [];
            const ids = [
                asString(item.advisoryId),
                asString(item.cve),
                ...sources.map((s) => asString(asRecord(s).remoteId)),
            ].filter((id) => id !== '');
            found.push({
                ids,
                severity: asString(item.severity).toLowerCase() || 'unknown',
                pkg,
                title: asString(item.title),
            });
        }
    }
    return found;
}

/**
 * @param {unknown} report parsed `npm audit --json`
 * @returns {Advisory[]}
 */
export function npmAdvisories(report) {
    /** @type {Advisory[]} */
    const found = [];
    const seen = new Set();
    const vulnerabilities = asRecord(asRecord(report).vulnerabilities);
    for (const [pkg, raw] of Object.entries(vulnerabilities)) {
        const via = asRecord(raw).via;
        for (const entry of Array.isArray(via) ? via : []) {
            // Strings are links to another vulnerable package; that package lists the advisory itself.
            if (typeof entry === 'string') continue;
            const item = asRecord(entry);
            const ghsa = asString(item.url).split('/').pop() ?? '';
            const ids = [
                ghsa,
                typeof item.source === 'number' ||
                typeof item.source === 'string'
                    ? String(item.source)
                    : '',
            ].filter((id) => id !== '');
            const key = ids.join('|');
            if (seen.has(key)) continue;
            seen.add(key);
            found.push({
                ids,
                severity: asString(item.severity).toLowerCase() || 'unknown',
                pkg: asString(item.name) || pkg,
                title: asString(item.title),
            });
        }
    }
    return found;
}

/**
 * @param {unknown} file parsed ignore file
 * @param {string} tool
 * @param {Date} now
 * @returns {{ active: Ignore[], problems: string[] }}
 */
export function loadIgnores(file, tool, now) {
    /** @type {Ignore[]} */
    const active = [];
    /** @type {string[]} */
    const problems = [];
    const list = asRecord(file).ignores;
    for (const raw of Array.isArray(list) ? list : []) {
        const item = asRecord(raw);
        const entry = {
            id: asString(item.id),
            tool: asString(item.tool),
            package: asString(item.package),
            reason: asString(item.reason),
            reviewed: asString(item.reviewed),
            expires: asString(item.expires),
        };
        const valid =
            entry.id !== '' &&
            ['composer', 'npm'].includes(entry.tool) &&
            entry.reason.length >= 10 &&
            /^\d{4}-\d{2}-\d{2}$/.test(entry.reviewed) &&
            /^\d{4}-\d{2}-\d{2}$/.test(entry.expires);
        if (!valid) {
            problems.push(
                `Invalid ignore entry "${entry.id || '?'}": id, tool, reason (10+ chars), reviewed and expires (YYYY-MM-DD) are required.`,
            );
            continue;
        }
        if (entry.tool !== tool) continue;
        if (new Date(`${entry.expires}T23:59:59Z`) < now) {
            problems.push(
                `Ignore entry ${entry.id} expired on ${entry.expires}: review it again or fix the advisory.`,
            );
            continue;
        }
        active.push(entry);
    }
    return { active, problems };
}

/**
 * @param {Advisory[]} advisories
 * @param {Ignore[]} ignores
 * @returns {{ blocking: Advisory[], ignored: Advisory[], informational: Advisory[] }}
 */
export function classify(advisories, ignores) {
    const ignoredIds = new Set(ignores.map((i) => i.id));
    /** @type {Advisory[]} */ const blocking = [];
    /** @type {Advisory[]} */ const ignored = [];
    /** @type {Advisory[]} */ const informational = [];
    for (const advisory of advisories) {
        if (!BLOCKING.has(advisory.severity)) informational.push(advisory);
        else if (advisory.ids.some((id) => ignoredIds.has(id)))
            ignored.push(advisory);
        else blocking.push(advisory);
    }
    return { blocking, ignored, informational };
}

/** @param {Advisory} a */
const describe = (a) =>
    `${a.severity.toUpperCase()} ${a.pkg} ${a.ids.join(', ')} ${a.title}`.trim();

/**
 * @param {string[]} argv
 * @param {Date} [now]
 * @returns {{ code: number, lines: string[] }}
 */
export function run(argv, now = new Date()) {
    /** @param {string} name */
    const option = (name) => {
        const index = argv.indexOf(`--${name}`);
        return index === -1 ? undefined : argv[index + 1];
    };
    const tool = option('tool') ?? '';
    const reportPath = option('report');
    const ignorePath = option('ignore') ?? '.github/audit-ignore.json';
    if (!['composer', 'npm'].includes(tool) || reportPath === undefined) {
        return {
            code: 2,
            lines: [
                'Usage: audit-gate.mjs --tool composer|npm --report <file.json> [--ignore <file.json>]',
            ],
        };
    }
    /** @type {unknown} */ let report;
    /** @type {unknown} */ let ignoreFile;
    try {
        report = JSON.parse(readFileSync(reportPath, 'utf8'));
        ignoreFile = JSON.parse(readFileSync(ignorePath, 'utf8'));
    } catch (error) {
        return {
            code: 2,
            lines: [
                `Cannot read input: ${error instanceof Error ? error.message : String(error)}`,
            ],
        };
    }
    const advisories =
        tool === 'composer'
            ? composerAdvisories(report)
            : npmAdvisories(report);
    const { active, problems } = loadIgnores(ignoreFile, tool, now);
    const { blocking, ignored, informational } = classify(advisories, active);
    const lines = [
        ...problems,
        ...informational.map((a) => `info (not blocking): ${describe(a)}`),
        ...ignored.map((a) => `ignored (reviewed): ${describe(a)}`),
        ...blocking.map((a) => `BLOCKING: ${describe(a)}`),
    ];
    const failed = blocking.length > 0 || problems.length > 0;
    lines.push(
        failed
            ? `${tool} audit gate FAILED: ${String(blocking.length)} blocking advisory(ies), ${String(problems.length)} ignore-file problem(s).`
            : `${tool} audit gate passed (${String(ignored.length)} ignored, ${String(informational.length)} below high).`,
    );
    return { code: failed ? 1 : 0, lines };
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
    const { code, lines } = run(process.argv.slice(2));
    for (const line of lines) process.stderr.write(`${line}\n`);
    process.exit(code);
}
