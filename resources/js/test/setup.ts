// Global setup for every Vitest test (vite.config.ts → test.setupFiles).
import type { TestingLibraryMatchers } from '@testing-library/jest-dom/matchers';
import * as jestDomMatchers from '@testing-library/jest-dom/matchers';
import { cleanup } from '@testing-library/react';
import { afterEach, expect } from 'vite-plus/test';
import './axe';

// jsdom has no canvas and logs "Not implemented: HTMLCanvasElement's getContext()" whenever
// something (axe-core does) probes it. Return null, which is what a browser returns for an
// unsupported context, so the probe falls back quietly.
if (typeof HTMLCanvasElement !== 'undefined') {
    HTMLCanvasElement.prototype.getContext = () => null;
}

// Testing Library only unmounts automatically when Vitest globals are on; they are off here
// (explicit imports from 'vite-plus/test'), so unmount after each test ourselves.
afterEach(() => {
    cleanup();
});

// Not '@testing-library/jest-dom/vitest': its type augmentation declares `Assertion<T = any>`, which no longer
// merges with Vitest 5's `Assertion<R, T>`. Register the matchers and augment the types here instead.
expect.extend(jestDomMatchers);

declare module 'vitest' {
    // Type parameters must match Vitest's own `Assertion<R, T>` for declaration merging; `R` is unused here.
    interface Assertion<
        R extends void | Promise<void> = void,
        T = unknown,
    > extends TestingLibraryMatchers<unknown, T> {
        toHaveNoA11yViolations: () => Promise<void>;
    }
}
