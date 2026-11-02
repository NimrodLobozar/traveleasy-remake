/**
 * The per-request CSP nonce (TE-015). The server puts it on `<meta property="csp-nonce" nonce="...">`
 * in the document head; it is not an Inertia prop, because every XHR visit would carry a nonce that
 * no longer matches the page's header. Read the `.nonce` property: browsers hide the attribute
 * (`getAttribute('nonce')` returns '') once the page is parsed. Browser only; returns `undefined`
 * when there is no document (SSR needs no nonce) or no meta tag.
 */
export function readCspNonce(): string | undefined {
    if (typeof document === 'undefined') {
        return undefined;
    }

    const meta = document.querySelector<HTMLMetaElement>(
        'meta[property="csp-nonce"]',
    );

    return meta?.nonce || undefined;
}
