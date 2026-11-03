import { useEffect, useId, useRef } from 'react';
import { useTranslation } from '@/lib/i18n';

export type FormError = {
    /** DOM id of the field (pass the same value as the field's `id`). */
    fieldId: string;
    label: string;
    message: string;
};

/**
 * Error summary (TE-022, system.md 4.3). Not a live region: when `focusSignal` changes (increase it
 * on every failed submit) and there are errors, focus moves to the summary, which announces it.
 * Each entry links to its field and focuses it.
 */
export function FormErrorSummary({
    errors,
    focusSignal,
}: {
    errors: readonly FormError[];
    focusSignal: number;
}) {
    const { t } = useTranslation();
    const ref = useRef<HTMLElement>(null);
    const titleId = useId();
    const hasErrors = errors.length > 0;

    useEffect(() => {
        if (focusSignal > 0 && hasErrors) {
            ref.current?.focus();
        }
    }, [focusSignal, hasErrors]);

    if (!hasErrors) {
        return null;
    }

    return (
        <section
            ref={ref}
            // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role -- spec 4.3 asks for a group; fieldset/details would add wrong semantics
            role="group"
            tabIndex={-1}
            aria-labelledby={titleId}
            className="rounded-md border-2 border-danger bg-surface p-4"
        >
            <h2 id={titleId} className="mb-2 text-lg font-bold text-text">
                {t('form.summary.title', { count: errors.length })}
            </h2>
            <ul className="list-disc space-y-1 pl-5">
                {errors.map((error) => (
                    <li key={error.fieldId}>
                        <a
                            href={`#${error.fieldId}`}
                            className="text-danger underline underline-offset-[3px] hover:decoration-[3px]"
                            onClick={(event) => {
                                event.preventDefault();
                                document.getElementById(error.fieldId)?.focus();
                            }}
                        >
                            {error.label}: {error.message}
                        </a>
                    </li>
                ))}
            </ul>
        </section>
    );
}
