import { CircleAlertIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { FieldError, Label, Text } from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';

/** Border, fill and state styling shared by every field control (system.md 4.3). */
export const CONTROL_CLASS =
    'min-h-12 w-full rounded-md border-2 border-input bg-surface px-3 text-text placeholder:text-text-muted ' +
    'hover:border-text-muted focus:border-brand ' +
    'data-[invalid]:border-danger data-[disabled]:cursor-not-allowed data-[disabled]:border-dashed data-[disabled]:border-line data-[disabled]:bg-surface-2 data-[disabled]:text-text-muted ' +
    'read-only:border-dashed read-only:bg-bg';

export function FieldLabel({
    children,
    isOptional,
}: {
    children: ReactNode;
    isOptional?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <Label className="mb-1 block font-bold text-text">
            {children}
            {isOptional && (
                <span className="ml-1 font-normal text-text-muted">
                    {t('ui.optional')}
                </span>
            )}
        </Label>
    );
}

export function FieldHelp({ children }: { children: ReactNode }) {
    return (
        <Text slot="description" className="mt-1 block text-sm text-text-muted">
            {children}
        </Text>
    );
}

/** Error text always carries an icon, never colour alone (WCAG 1.4.1). */
export function FieldErrorText({ children }: { children: ReactNode }) {
    return (
        <FieldError className="mt-1 flex items-start gap-1.5 text-sm font-medium text-danger">
            <CircleAlertIcon
                aria-hidden="true"
                className="mt-0.5 size-4 shrink-0"
            />
            <span>{children}</span>
        </FieldError>
    );
}
