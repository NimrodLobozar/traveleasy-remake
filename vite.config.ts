import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, lazyPlugins } from 'vite-plus';

// Vite dev-server port, inside the container and on 127.0.0.1 of the host (VITE_PORT in .env).
const devServerPort = Number(process.env.VITE_PORT ?? 5173);

// No `fonts` option: the kit's bunny() helper downloads fonts from a third-party host at build
// time. We use a system font stack (resources/css/app.css) until the design system picks a
// self-hosted font (CLAUDE.md: no runtime CDNs or third-party fonts).
export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    // Dev server settings come from env (set by compose.dev.yaml; documented in .env.example).
    // In Docker, Vite listens on 0.0.0.0 inside the container, but the browser reaches it through
    // the 127.0.0.1-only published port, so the HMR host is `localhost`; laravel-vite-plugin derives
    // the dev-server URL in public/hot (and its CORS allow-list from APP_URL) from it.
    // Polling: file events from a Windows (NTFS) bind mount don't reach the Linux container, so the
    // watcher polls. Turn VITE_USE_POLLING off when the repo lives in the WSL file system.
    server: {
        host: process.env.VITE_HOST ?? 'localhost',
        port: devServerPort,
        strictPort: true,
        hmr: {
            host: 'localhost',
            clientPort: devServerPort,
        },
        watch: {
            usePolling: process.env.VITE_USE_POLLING === 'true',
            interval: Number(process.env.VITE_POLLING_INTERVAL ?? 1000),
            binaryInterval: Number(process.env.VITE_POLLING_INTERVAL ?? 1000),
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/.git/**',
                '**/vendor/**',
                '**/node_modules/**',
                '**/storage/**',
                '**/bootstrap/cache/**',
                '**/public/build/**',
                '**/public/hot',
                '**/docs/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            'resources/js/types/generated/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/types/generated/**',
            'resources/js/wayfinder/**',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
