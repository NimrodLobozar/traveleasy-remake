import { MinusIcon, PlusIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Button as AriaButton,
    Group,
    I18nProvider,
    Input,
    NumberField as AriaNumberField,
} from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    CONTROL_CLASS,
    FieldErrorText,
    FieldHelp,
    FieldLabel,
} from './field-parts';

export type NumberFieldProps = {
    label: string;
    name?: string;
    description?: ReactNode;
    error?: string;
    /** Accessible names for the stepper buttons, e.g. "Minder volwassenen" / "Meer volwassenen". */
    decrementLabel?: string;
    incrementLabel?: string;
    minValue?: number;
    maxValue?: number;
    step?: number;
    value?: number;
    defaultValue?: number;
    onChange?: (value: number) => void;
    isOptional?: boolean;
    isRequired?: boolean;
    isDisabled?: boolean;
    className?: string;
};

const STEPPER =
    'flex size-12 shrink-0 items-center justify-center rounded-md border-2 border-border bg-surface text-text hover:border-text hover:bg-surface-2 ' +
    'data-[disabled]:cursor-not-allowed data-[disabled]:border-dashed data-[disabled]:border-line data-[disabled]:bg-surface-2 data-[disabled]:text-text-muted';

/**
 * NumberField (TE-022, system.md 4.7) on RAC: spinbutton role, arrow/Page/Home/End keys, stepper
 * buttons (48 px), typing and display in the active locale (nl `23,5`, en `23.5`). Min/max are
 * enforced by RAC on commit; when both are set and no description is given, the range text is shown
 * as help so the limits are visible before any error (WCAG 3.3.2).
 */
export function NumberField({
    label,
    description,
    error,
    decrementLabel,
    incrementLabel,
    isOptional,
    className,
    ...props
}: NumberFieldProps) {
    const { t, locale } = useTranslation();
    const { minValue, maxValue } = props;
    const help =
        description ??
        (minValue !== undefined && maxValue !== undefined
            ? t('ui.number.range', {
                  min: String(minValue),
                  max: String(maxValue),
              })
            : undefined);

    return (
        <I18nProvider locale={locale}>
            <AriaNumberField
                {...props}
                isInvalid={error !== undefined && error !== ''}
                validationBehavior="aria"
                className={cn('block', className)}
            >
                <FieldLabel isOptional={isOptional}>{label}</FieldLabel>
                <Group className="flex items-center gap-2">
                    <AriaButton
                        slot="decrement"
                        aria-label={decrementLabel ?? t('ui.number.decrement')}
                        className={STEPPER}
                    >
                        <MinusIcon aria-hidden="true" className="size-5" />
                    </AriaButton>
                    <Input
                        className={cn(
                            CONTROL_CLASS,
                            'w-[4.5rem] px-1 text-center tabular-nums',
                        )}
                    />
                    <AriaButton
                        slot="increment"
                        aria-label={incrementLabel ?? t('ui.number.increment')}
                        className={STEPPER}
                    >
                        <PlusIcon aria-hidden="true" className="size-5" />
                    </AriaButton>
                </Group>
                {help !== undefined && <FieldHelp>{help}</FieldHelp>}
                <FieldErrorText>{error}</FieldErrorText>
            </AriaNumberField>
        </I18nProvider>
    );
}
