/**
 * Hosts a browser test may talk to (TE-029 AC1). Everything else is an external origin: a hostname
 * (a CDN, a tracker, a font service) or a raw IP address. The Chromium resolver rule in
 * playwright.config.ts already makes hostnames fail to resolve; it does not cover raw IPs, and a
 * failed request would not fail a test by itself. The `externalRequests` fixture in base-test.ts
 * turns any such request into a test failure.
 */
const LOCAL_HOSTS = new Set(['localhost', '127.0.0.1', '[::1]']);

/** True for URLs that never leave the machine; non-network schemes (data:, blob:, about:) count as local. */
export function isLocalRequest(rawUrl: string): boolean {
    let url: URL;
    try {
        url = new URL(rawUrl);
    } catch {
        // Not a parseable absolute URL: treat as external rather than silently passing.
        return false;
    }
    if (!['http:', 'https:', 'ws:', 'wss:', 'ftp:'].includes(url.protocol)) {
        return ['data:', 'blob:', 'about:'].includes(url.protocol);
    }

    return LOCAL_HOSTS.has(url.hostname);
}
