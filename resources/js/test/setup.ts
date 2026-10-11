// Global setup for every Vitest test (vite.config.ts → test.setupFiles).
import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vite-plus/test';
import './axe';

// jsdom has no canvas and logs "Not implemented: HTMLCanvasElement's getContext()" whenever
// something (axe-core does) probes it. Return null, which is what a browser returns for an
// unsupported context, so the probe falls back quietly.
HTMLCanvasElement.prototype.getContext = () => null;

// Testing Library only unmounts automatically when Vitest globals are on; they are off here
// (explicit imports from 'vite-plus/test'), so unmount after each test ourselves.
afterEach(() => {
    cleanup();
});
