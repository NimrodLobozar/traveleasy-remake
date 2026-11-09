#!/usr/bin/env node
// PreToolUse hook (owner rule: token budget). Blocks reading a whole LARGE file in one go, so agents look
// the spot up first (memory MCP: find_symbol / file_outline) and read only the lines they need.
// - Read: blocked when the file has more than MAX_LINES lines and no limit <= MAX_LINES is given.
// - Bash/PowerShell: blocked when a bare cat/type/Get-Content/less/more dumps more than MAX_LINES lines.
// Ranged or filtered reads (Read offset/limit, sed -n 'a,bp', head/tail -n N, `cat f | grep x`) stay allowed.
// Exit 2 = block; stderr goes back to the agent as the reason. Any error inside the hook = allow (fail open).
import { readFileSync, statSync } from 'node:fs';
import { extname, isAbsolute, resolve } from 'node:path';

const MAX_LINES = Number(process.env.TOKEN_GUARD_MAX_LINES ?? 300);
const BINARY = new Set([
    '.png',
    '.jpg',
    '.jpeg',
    '.gif',
    '.webp',
    '.svg',
    '.ico',
    '.pdf',
    '.ipynb',
    '.woff',
    '.woff2',
]);
const DUMP = new Set([
    'cat',
    'type',
    'less',
    'more',
    'nl',
    'get-content',
    'gc',
]);
const LIMITED = /(-totalcount|-head|-tail|-first|-last)\b/i;

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * @param {string} file
 * @returns {number} line count, or 0 when it isn't a readable regular file
 */
function lineCount(file) {
    try {
        const st = statSync(file);
        if (!st.isFile() || st.size > 20_000_000) return 0;
        let n = 1;
        for (const b of readFileSync(file)) if (b === 10) n++;
        return n;
    } catch {
        return 0;
    }
}

/**
 * @param {string} what
 * @returns {never}
 */
function block(what) {
    const max = String(MAX_LINES);
    process.stderr.write(
        `Token guard: ${what}. Find the spot first with mcp__memory__find_symbol / search_code / ` +
            `file_outline (project "traveleasy-remake"), then read only those lines with ` +
            `mcp__memory__read_range, or Read with offset+limit (max ${max} lines). If you really need ` +
            `the whole file, read it in chunks with offset/limit.\n`,
    );
    process.exit(2);
}

/**
 * Very small shell tokenizer: enough to find the file arguments of cat-like commands.
 * @param {string} segment
 * @returns {string[]}
 */
function tokens(segment) {
    /** @type {string[]} */
    const out = [];
    for (const m of segment.matchAll(/"([^"]*)"|'([^']*)'|(\S+)/g)) {
        const t = m[1] ?? m[2] ?? m[3];
        if (t !== undefined) out.push(t);
    }
    return out;
}

/**
 * @param {string} command
 * @param {string} cwd
 */
function checkShell(command, cwd) {
    let dir = cwd;
    let total = 0;
    /** @type {string[]} */
    const files = [];
    for (const raw of command.split(/&&|\|\||;|\n/)) {
        // Only a bare dump counts: if the output is piped on (grep, head, …) it's already filtered.
        const parts = raw.split('|');
        const seg = (parts[0] ?? '').trim();
        const t = tokens(seg);
        const head = (t[0] ?? '').toLowerCase();
        const target = t[1];
        if (
            (head === 'cd' || head === 'set-location') &&
            target !== undefined
        ) {
            dir = isAbsolute(target) ? target : resolve(dir, target);
            continue;
        }
        if (!DUMP.has(head) || parts.length > 1) continue;
        if (/[<>]/.test(seg) || LIMITED.test(seg)) continue; // writes a file, heredoc, or already limited
        for (const arg of t.slice(1)) {
            if (arg.startsWith('-') || arg.includes('$') || arg.includes('*'))
                continue;
            const n = lineCount(isAbsolute(arg) ? arg : resolve(dir, arg));
            if (n > 0) {
                total += n;
                files.push(`${arg} (${String(n)})`);
            }
        }
    }
    if (total > MAX_LINES) {
        block(`this command dumps ${String(total)} lines: ${files.join(', ')}`);
    }
}

try {
    /** @type {unknown} */
    const parsed = JSON.parse(readFileSync(0, 'utf8'));
    const input = isRecord(parsed) ? parsed : {};
    const tool =
        typeof input['tool_name'] === 'string' ? input['tool_name'] : '';
    const ti = isRecord(input['tool_input']) ? input['tool_input'] : {};
    const cwd = typeof input['cwd'] === 'string' ? input['cwd'] : process.cwd();
    const filePath = ti['file_path'];
    const command = ti['command'];
    if (tool === 'Read' && typeof filePath === 'string') {
        const limit = ti['limit'];
        const ranged = typeof limit === 'number' && limit <= MAX_LINES;
        if (!ranged && !BINARY.has(extname(filePath).toLowerCase())) {
            const n = lineCount(
                isAbsolute(filePath) ? filePath : resolve(cwd, filePath),
            );
            if (n > MAX_LINES) block(`${filePath} has ${String(n)} lines`);
        }
    } else if (
        (tool === 'Bash' || tool === 'PowerShell') &&
        typeof command === 'string'
    ) {
        checkShell(command, cwd);
    }
} catch {
    // Fail open: never break the agent because of the guard itself.
}
process.exit(0);
