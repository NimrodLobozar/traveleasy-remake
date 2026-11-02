#!/usr/bin/env node
// Production compose guard (TE-013 AC3, TE-024 AC7/AC11/AC13/AC14/AC17). Reads the rendered model of the
// PRODUCTION compose file and fails on anything that must never ship:
//
//   docker compose -f compose.yaml --env-file deploy/portainer.env.example config --format json \
//     | node scripts/ci/compose-guard.mjs
//   node scripts/ci/compose-guard.mjs <config.json>
//
// Exit code: 0 = pass, 1 = violations (one line each), 2 = unreadable input.
// The rendered JSON is used on purpose: it is what Docker will really run (anchors, env_file and ${VAR} resolved).
import { readFileSync } from 'node:fs';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

export const NPM_NETWORK_NAME = 'npm_shared';
export const NPM_ALIAS = 'traveleasy-app';
export const WEB_SERVICE = 'app';
export const SECRET_NAMES = [
    'APP_KEY',
    'DB_SUPERUSER_PASSWORD',
    'DB_PASSWORD',
    'SUPER_ADMIN_PASSWORD_HASH',
    'SUPER_ADMIN_TOTP_SECRET',
];
/** Services that may keep a writable root filesystem (documented in compose.yaml). */
const WRITABLE_ROOT_ALLOWED = new Set(['db']);
/** One-shot services: no restart policy or health check expected. */
const ONE_SHOT = new Set(['db-init']);

/**
 * @param {unknown} value
 * @returns {Record<string, any>}
 */
const rec = (value) =>
    typeof value === 'object' && value !== null && !Array.isArray(value)
        ? /** @type {Record<string, any>} */ (value)
        : {};

/**
 * @param {unknown} model rendered compose model (`docker compose config --format json`)
 * @returns {string[]} violations
 */
export function checkCompose(model) {
    /** @type {string[]} */
    const v = [];
    const root = rec(model);
    const services = rec(root.services);
    const networks = rec(root.networks);

    if (Object.keys(services).length === 0) {
        return ['The compose model has no services.'];
    }

    for (const [name, raw] of Object.entries(services)) {
        const s = rec(raw);
        const image = typeof s.image === 'string' ? s.image : '';

        if (/adminer/i.test(name) || /adminer/i.test(image))
            v.push(`${name}: Adminer must not be part of the production file.`);
        if (Array.isArray(s.ports) && s.ports.length > 0)
            v.push(`${name}: publishes host ports; production publishes none.`);
        if (s.build !== undefined)
            v.push(
                `${name}: has a build section; production pulls a pinned image.`,
            );
        if (s.env_file !== undefined)
            v.push(`${name}: uses env_file; list the variables explicitly.`);
        if (s.network_mode !== undefined)
            v.push(`${name}: network_mode bypasses the network contract.`);
        if (s.privileged === true) v.push(`${name}: privileged.`);

        const tag = image.split('@')[0]?.split('/').pop() ?? '';
        if (image === '') v.push(`${name}: no image.`);
        else if (!tag.includes(':') || tag.endsWith(':latest'))
            v.push(`${name}: image "${image}" is untagged or uses :latest.`);

        for (const volume of Array.isArray(s.volumes) ? s.volumes : []) {
            const target = String(rec(volume).target ?? '');
            if (target.includes('docker-entrypoint-initdb.d'))
                v.push(`${name}: mounts an initdb directory (${target}).`);
            if (rec(volume).type === 'bind')
                v.push(
                    `${name}: bind mount to ${String(rec(volume).source)}; production uses named volumes and tmpfs only.`,
                );
        }

        // Network contract: only the web service joins npm_shared, with the alias.
        const joined = Object.keys(rec(s.networks));
        const onNpm = joined.filter(
            (key) => rec(networks[key]).name === NPM_NETWORK_NAME,
        );
        if (name === WEB_SERVICE) {
            if (onNpm.length !== 1)
                v.push(`${name}: must join the ${NPM_NETWORK_NAME} network.`);
            else {
                const aliases = /** @type {unknown} */ (
                    rec(rec(s.networks)[onNpm[0] ?? '']).aliases
                );
                if (!Array.isArray(aliases) || !aliases.includes(NPM_ALIAS))
                    v.push(
                        `${name}: needs the network alias ${NPM_ALIAS} on ${NPM_NETWORK_NAME}.`,
                    );
            }
            if (!joined.includes('default'))
                v.push(
                    `${name}: must also join the default network (database, ssr).`,
                );
        } else if (onNpm.length > 0) {
            v.push(
                `${name}: joins ${NPM_NETWORK_NAME}; only ${WEB_SERVICE} may.`,
            );
        }

        // Hardening (ADR-0002 D8).
        if (!(Array.isArray(s.cap_drop) && s.cap_drop.includes('ALL')))
            v.push(`${name}: cap_drop must be [ALL].`);
        if (
            !(
                Array.isArray(s.security_opt) &&
                s.security_opt.includes('no-new-privileges:true')
            )
        )
            v.push(
                `${name}: security_opt must contain no-new-privileges:true.`,
            );
        if (s.read_only !== true && !WRITABLE_ROOT_ALLOWED.has(name))
            v.push(`${name}: read_only must be true.`);
        if (
            s.read_only === true &&
            !(Array.isArray(s.tmpfs) && s.tmpfs.length > 0) &&
            name !== 'db-init'
        )
            v.push(`${name}: read-only root filesystem needs tmpfs paths.`);
        for (const key of [
            'mem_limit',
            'memswap_limit',
            'cpus',
            'pids_limit',
        ]) {
            if (s[key] === undefined || s[key] === null || s[key] === 0)
                v.push(`${name}: ${key} is not set.`);
        }
        if (
            s.mem_limit !== undefined &&
            s.memswap_limit !== undefined &&
            String(s.mem_limit) !== String(s.memswap_limit)
        )
            v.push(`${name}: memswap_limit must equal mem_limit (no swap).`);
        if (!ONE_SHOT.has(name)) {
            if (s.restart !== 'unless-stopped')
                v.push(`${name}: restart must be unless-stopped.`);
            if (rec(s.healthcheck).test === undefined)
                v.push(`${name}: no health check.`);
        }
        if (rec(rec(s.logging).options)['max-size'] === undefined)
            v.push(`${name}: log driver has no max-size cap.`);

        // Secrets: the superuser password only reaches db and db-init; ssr gets none at all.
        const envNames = Object.keys(rec(s.environment));
        if (name === 'ssr') {
            for (const secret of SECRET_NAMES)
                if (envNames.includes(secret))
                    v.push(
                        `ssr: environment contains ${secret}; ssr holds no secrets.`,
                    );
        }
        if (
            envNames.includes('DB_SUPERUSER_PASSWORD') &&
            !['db', 'db-init'].includes(name)
        )
            v.push(
                `${name}: receives DB_SUPERUSER_PASSWORD; only db and db-init may.`,
            );

        if (['worker', 'scheduler'].includes(name)) {
            if (rec(rec(s.depends_on).app).condition !== 'service_healthy')
                v.push(
                    `${name}: must depend on app with condition service_healthy.`,
                );
        }
    }

    // Same image for app and ssr.
    const app = rec(services[WEB_SERVICE]);
    const ssr = rec(services.ssr);
    if (services.ssr !== undefined && app.image !== ssr.image)
        v.push('app and ssr must use the same image reference.');
    if (services.ssr !== undefined) {
        const command = Array.isArray(ssr.command)
            ? ssr.command.join(' ')
            : String(ssr.command ?? '');
        const entry = Array.isArray(ssr.entrypoint)
            ? ssr.entrypoint.join(' ')
            : String(ssr.entrypoint ?? '');
        if (!`${entry} ${command}`.includes('ssr'))
            v.push('ssr: must run the ssr role (node bootstrap/ssr/ssr.js).');
    }

    // Network declarations.
    if (rec(networks.default).internal !== true)
        v.push(
            'The default network must be internal: true (no egress for db, ssr).',
        );
    const npm = Object.values(networks)
        .map(rec)
        .filter((n) => n.name === NPM_NETWORK_NAME);
    if (npm.length !== 1 || npm[0]?.external !== true)
        v.push(
            `The ${NPM_NETWORK_NAME} network must be declared exactly once as external.`,
        );

    return v;
}

/** @returns {string} */
const readStdin = () => readFileSync(0, 'utf8');

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
    /** @type {unknown} */
    let model;
    try {
        const file = process.argv[2];
        model = JSON.parse(
            file === undefined ? readStdin() : readFileSync(file, 'utf8'),
        );
    } catch (error) {
        process.stderr.write(
            `Cannot read the compose model: ${error instanceof Error ? error.message : String(error)}\n`,
        );
        process.exit(2);
    }
    const violations = checkCompose(model);
    for (const line of violations)
        process.stderr.write(`compose guard: ${line}\n`);
    process.stderr.write(
        violations.length === 0
            ? 'compose guard passed.\n'
            : `compose guard FAILED: ${String(violations.length)} violation(s).\n`,
    );
    process.exit(violations.length === 0 ? 0 : 1);
}
