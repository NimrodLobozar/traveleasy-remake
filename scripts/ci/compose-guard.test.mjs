import { describe, expect, it } from 'vitest';
import { checkCompose } from './compose-guard.mjs';

/** @typedef {Record<string, unknown>} Model */

const hardened = {
    restart: 'unless-stopped',
    cap_drop: ['ALL'],
    security_opt: ['no-new-privileges:true'],
    read_only: true,
    tmpfs: ['/tmp'],
    mem_limit: '268435456',
    memswap_limit: '268435456',
    cpus: 1,
    pids_limit: 100,
    healthcheck: { test: ['CMD', 'true'] },
    logging: {
        driver: 'json-file',
        options: { 'max-size': '10m', 'max-file': '3' },
    },
    networks: { default: null },
};

const image = 'ghcr.io/nimrodlobozar/traveleasy:v0.1.0';
const postgres = 'postgres:18.6-alpine@sha256:abc';

/** @returns {Model} */
const good = () => ({
    services: {
        app: {
            ...hardened,
            image,
            networks: {
                default: null,
                npm_network: { aliases: ['traveleasy-app'] },
            },
            environment: { APP_KEY: 'x' },
        },
        ssr: {
            ...hardened,
            image,
            command: ['ssr'],
            environment: { NODE_ENV: 'production' },
        },
        db: {
            ...hardened,
            read_only: false,
            image: postgres,
            environment: { POSTGRES_PASSWORD: 'x' },
        },
        'db-init': {
            ...hardened,
            restart: undefined,
            healthcheck: undefined,
            image: postgres,
            environment: { DB_SUPERUSER_PASSWORD: 'x' },
        },
    },
    networks: {
        default: { internal: true },
        npm_network: { name: 'npm_shared', external: true },
    },
});

/**
 * Sets (or, with `undefined`, removes) a value at a dotted path, creating objects on the way.
 * @param {Model} model
 * @param {string} path
 * @param {unknown} value
 */
const set = (model, path, value) => {
    const keys = path.split('.');
    const last = keys.pop() ?? '';
    /** @type {Model} */
    let current = model;
    for (const key of keys) {
        const next = current[key];
        if (typeof next !== 'object' || next === null) current[key] = {};
        current = /** @type {Model} */ (current[key]);
    }
    if (value === undefined) Reflect.deleteProperty(current, last);
    else current[last] = value;
};

/**
 * @param {Array<[string, unknown]>} changes
 * @returns {string}
 */
const violations = (...changes) => {
    const model = good();
    for (const [path, value] of changes) set(model, path, value);
    return checkCompose(model).join('\n');
};

describe('compose guard', () => {
    it('accepts a correct production model', () => {
        expect(checkCompose(good())).toEqual([]);
    });

    it('fails on an adminer service', () => {
        expect(
            violations([
                'services.adminer',
                { ...hardened, image: 'adminer:6.1.1' },
            ]),
        ).toContain('Adminer');
    });

    it('fails on any ports entry, db included', () => {
        expect(
            violations([
                'services.db.ports',
                [{ target: 5432, published: '5432' }],
            ]),
        ).toContain('db: publishes host ports');
        expect(
            violations(['services.app.ports', [{ target: 8080 }]]),
        ).toContain('app: publishes host ports');
    });

    it('fails on an initdb mount and on bind mounts', () => {
        expect(
            violations([
                'services.db.volumes',
                [
                    {
                        type: 'bind',
                        source: './x',
                        target: '/docker-entrypoint-initdb.d',
                    },
                ],
            ]),
        ).toContain('initdb');
        expect(
            violations([
                'services.app.volumes',
                [{ type: 'bind', source: '/srv', target: '/srv' }],
            ]),
        ).toContain('bind mount');
    });

    it('fails on latest, untagged images and build sections', () => {
        expect(
            violations(['services.app.image', 'ghcr.io/x/y:latest']),
        ).toContain(':latest');
        expect(violations(['services.app.image', 'ghcr.io/x/y'])).toContain(
            'untagged',
        );
        expect(violations(['services.app.build', { context: '.' }])).toContain(
            'build section',
        );
    });

    it('fails when a service other than app joins npm_shared, or app lacks the alias', () => {
        expect(
            violations([
                'services.ssr.networks',
                { default: null, npm_network: {} },
            ]),
        ).toContain('ssr: joins npm_shared');
        expect(violations(['services.app.networks.npm_network', {}])).toContain(
            'alias traveleasy-app',
        );
    });

    it('fails when default is not internal or npm_shared is not external', () => {
        expect(violations(['networks.default', {}])).toContain(
            'internal: true',
        );
        expect(
            violations(['networks.npm_network', { name: 'npm_shared' }]),
        ).toContain('external');
    });

    it('fails when ssr receives a secret or an env_file', () => {
        expect(violations(['services.ssr.environment.APP_KEY', 'x'])).toContain(
            'ssr: environment contains APP_KEY',
        );
        expect(violations(['services.ssr.env_file', ['.env']])).toContain(
            'env_file',
        );
    });

    it('keeps the superuser password away from app', () => {
        expect(
            violations(['services.app.environment.DB_SUPERUSER_PASSWORD', 'x']),
        ).toContain('only db and db-init');
    });

    it('fails on missing hardening flags and limits', () => {
        expect(violations(['services.app.cap_drop', []])).toContain('cap_drop');
        expect(violations(['services.app.security_opt', []])).toContain(
            'no-new-privileges',
        );
        expect(violations(['services.app.read_only', false])).toContain(
            'read_only',
        );
        expect(violations(['services.ssr.mem_limit', undefined])).toContain(
            'ssr: mem_limit is not set',
        );
        expect(violations(['services.ssr.memswap_limit', '999'])).toContain(
            'memswap_limit must equal',
        );
        expect(violations(['services.app.logging', undefined])).toContain(
            'max-size',
        );
    });

    it('requires worker and scheduler to wait for a healthy app', () => {
        expect(
            violations([
                'services.worker',
                {
                    ...hardened,
                    image,
                    depends_on: { app: { condition: 'service_started' } },
                },
            ]),
        ).toContain('worker: must depend on app');
    });

    it('requires app and ssr to share the image', () => {
        expect(violations(['services.ssr.image', 'ghcr.io/x/y:v2'])).toContain(
            'same image',
        );
    });
});
