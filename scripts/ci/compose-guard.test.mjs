import { describe, expect, it } from 'vitest';
import { checkCompose } from './compose-guard.mjs';

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
            image: 'postgres:18.6-alpine@sha256:abc',
            environment: { POSTGRES_PASSWORD: 'x' },
        },
        'db-init': {
            ...hardened,
            restart: undefined,
            healthcheck: undefined,
            image: 'postgres:18.6-alpine@sha256:abc',
            environment: { DB_SUPERUSER_PASSWORD: 'x' },
        },
    },
    networks: {
        default: { internal: true },
        npm_network: { name: 'npm_shared', external: true },
    },
});

const violations = (mutate) => {
    const model = good();
    mutate(model);
    return checkCompose(model).join('\n');
};

describe('compose guard', () => {
    it('accepts a correct production model', () => {
        expect(checkCompose(good())).toEqual([]);
    });

    it('fails on an adminer service', () => {
        expect(
            violations(
                (m) =>
                    (m.services.adminer = {
                        ...m.services.db,
                        image: 'adminer:6.1.1',
                    }),
            ),
        ).toContain('Adminer');
    });

    it('fails on any ports entry, db included', () => {
        expect(
            violations(
                (m) =>
                    (m.services.db.ports = [
                        { target: 5432, published: '5432' },
                    ]),
            ),
        ).toContain('db: publishes host ports');
        expect(
            violations((m) => (m.services.app.ports = [{ target: 8080 }])),
        ).toContain('app: publishes host ports');
    });

    it('fails on an initdb mount and on bind mounts', () => {
        expect(
            violations(
                (m) =>
                    (m.services.db.volumes = [
                        {
                            type: 'bind',
                            source: './x',
                            target: '/docker-entrypoint-initdb.d',
                        },
                    ]),
            ),
        ).toContain('initdb');
        expect(
            violations(
                (m) =>
                    (m.services.app.volumes = [
                        { type: 'bind', source: '/srv', target: '/srv' },
                    ]),
            ),
        ).toContain('bind mount');
    });

    it('fails on latest, untagged images and build sections', () => {
        expect(
            violations((m) => (m.services.app.image = 'ghcr.io/x/y:latest')),
        ).toContain(':latest');
        expect(
            violations((m) => (m.services.app.image = 'ghcr.io/x/y')),
        ).toContain('untagged');
        expect(
            violations((m) => (m.services.app.build = { context: '.' })),
        ).toContain('build section');
    });

    it('fails when a service other than app joins npm_shared, or app lacks the alias', () => {
        expect(
            violations(
                (m) =>
                    (m.services.ssr.networks = {
                        default: null,
                        npm_network: {},
                    }),
            ),
        ).toContain('ssr: joins npm_shared');
        expect(
            violations((m) => (m.services.app.networks.npm_network = {})),
        ).toContain('alias traveleasy-app');
    });

    it('fails when default is not internal or npm_shared is not external', () => {
        expect(violations((m) => (m.networks.default = {}))).toContain(
            'internal: true',
        );
        expect(
            violations(
                (m) => (m.networks.npm_network = { name: 'npm_shared' }),
            ),
        ).toContain('external');
    });

    it('fails when ssr receives a secret or an env_file', () => {
        expect(
            violations((m) => (m.services.ssr.environment.APP_KEY = 'x')),
        ).toContain('ssr: environment contains APP_KEY');
        expect(
            violations((m) => (m.services.ssr.env_file = ['.env'])),
        ).toContain('env_file');
    });

    it('keeps the superuser password away from app', () => {
        expect(
            violations(
                (m) => (m.services.app.environment.DB_SUPERUSER_PASSWORD = 'x'),
            ),
        ).toContain('only db and db-init');
    });

    it('fails on missing hardening flags and limits', () => {
        expect(violations((m) => (m.services.app.cap_drop = []))).toContain(
            'cap_drop',
        );
        expect(violations((m) => (m.services.app.security_opt = []))).toContain(
            'no-new-privileges',
        );
        expect(violations((m) => (m.services.app.read_only = false))).toContain(
            'read_only',
        );
        expect(violations((m) => delete m.services.ssr.mem_limit)).toContain(
            'ssr: mem_limit is not set',
        );
        expect(
            violations((m) => (m.services.ssr.memswap_limit = '999')),
        ).toContain('memswap_limit must equal');
        expect(violations((m) => delete m.services.app.logging)).toContain(
            'max-size',
        );
    });

    it('requires worker and scheduler to wait for a healthy app', () => {
        expect(
            violations(
                (m) =>
                    (m.services.worker = {
                        ...m.services.ssr,
                        depends_on: { app: { condition: 'service_started' } },
                    }),
            ),
        ).toContain('worker: must depend on app');
    });

    it('requires app and ssr to share the image', () => {
        expect(
            violations((m) => (m.services.ssr.image = 'ghcr.io/x/y:v2')),
        ).toContain('same image');
    });
});
