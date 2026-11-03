const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * The first focusable element that follows `container` in document order and is not inside it.
 * Used to put focus somewhere sensible before a dismissed alert or banner leaves the DOM (TE-022 AC6).
 * Event-handler use only; it reads the DOM.
 */
export function nextFocusableAfter(container: HTMLElement): HTMLElement | null {
    const candidates = document.querySelectorAll<HTMLElement>(FOCUSABLE);

    for (const candidate of candidates) {
        if (container.contains(candidate)) {
            continue;
        }

        if (
            container.compareDocumentPosition(candidate) &
            Node.DOCUMENT_POSITION_FOLLOWING
        ) {
            return candidate;
        }
    }

    return null;
}
