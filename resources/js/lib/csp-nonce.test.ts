import { afterEach, describe, expect, it } from 'vite-plus/test';
import { readCspNonce } from './csp-nonce';

afterEach(() => {
    document.head.innerHTML = '';
});

describe('readCspNonce', () => {
    it('returns undefined without a meta tag', () => {
        expect(readCspNonce()).toBeUndefined();
    });

    it('reads the nonce property of the csp-nonce meta tag', () => {
        const meta = document.createElement('meta');
        meta.setAttribute('property', 'csp-nonce');
        meta.nonce = 'abc123';
        document.head.append(meta);

        expect(readCspNonce()).toBe('abc123');
    });

    it('treats an empty nonce as none', () => {
        const meta = document.createElement('meta');
        meta.setAttribute('property', 'csp-nonce');
        document.head.append(meta);

        expect(readCspNonce()).toBeUndefined();
    });
});
