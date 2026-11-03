import { CheckIcon, ChevronDownIcon, CircleAlertIcon } from 'lucide-react';
import { useId, useSyncExternalStore } from 'react';
import type { Key, ReactNode } from 'react';
import {
    Button as AriaButton,
    ListBox,
    ListBoxItem,
    Popover,
    Select as AriaSelect,
    SelectValue,
} from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    CONTROL_CLASS,
    FieldErrorText,
    FieldHelp,
    FieldLabel,
} from './field-parts';
import { useBlurAnnounce } from './announcer';

/** True on touch devices (coarse primary pointer); false during SSR and first render, so hydration matches. */
function useCoarsePointer(): boolean {
    return useSyncExternalStore(
        (notify) => {
            if (typeof window.matchMedia !== 'function') {
                return () => undefined;
            }
            const query = window.matchMedia('(pointer: coarse)');
            query.addEventListener('change', notify);

            return () => {
                query.removeEventListener('change', notify);
            };
        },
        () =>
            typeof window.matchMedia === 'function' &&
            window.matchMedia('(pointer: coarse)').matches,
        () => false,
    );
}

/** Spec 4.4: on touch with 5 options or fewer use the native select. */
const NATIVE_MAX_OPTIONS = 5;

export type SelectOption = { id: string; label: string };

export type SelectProps = {
    label: string;
    options: readonly SelectOption[];
    name?: string;
    description?: ReactNode;
    error?: string;
    placeholder: string;
    isOptional?: boolean;
    isRequired?: boolean;
    isDisabled?: boolean;
    value?: string | null;
    defaultValue?: string;
    onChange?: (value: string) => void;
    className?: string;
};

/**
 * Select (TE-022, system.md 4.4) on RAC Select: button with `aria-haspopup="listbox"`, listbox of
 * 44 px options, selected option bold with a check, highlighted option on `--brand`. A native
 * `<select>` is rendered by RAC for form submission and autofill.
 */
export function Select({
    label,
    options,
    description,
    error,
    placeholder,
    isOptional,
    className,
    value,
    defaultValue,
    onChange,
    ...props
}: SelectProps) {
    const { t } = useTranslation();
    const coarse = useCoarsePointer();
    const blurAnnounce = useBlurAnnounce(label, error);
    const id = useId();
    const hasError = error !== undefined && error !== '';

    if (coarse && options.length <= NATIVE_MAX_OPTIONS) {
        const current = value ?? undefined;

        return (
            <div className={cn('block w-full', className)}>
                <label htmlFor={id} className="mb-1 block font-bold text-text">
                    {label}
                    {isOptional && (
                        <span className="ml-1 font-normal text-text-muted">
                            {t('ui.optional')}
                        </span>
                    )}
                </label>
                <select
                    id={id}
                    name={props.name}
                    required={props.isRequired}
                    disabled={props.isDisabled}
                    value={current}
                    defaultValue={
                        current === undefined ? (defaultValue ?? '') : undefined
                    }
                    aria-invalid={hasError || undefined}
                    aria-describedby={
                        [
                            description !== undefined ? `${id}-d` : null,
                            hasError ? `${id}-e` : null,
                        ]
                            .filter(Boolean)
                            .join(' ') || undefined
                    }
                    onChange={(event) => {
                        onChange?.(event.currentTarget.value);
                    }}
                    onBlur={blurAnnounce.onBlur}
                    onFocus={blurAnnounce.onFocus}
                    className={cn(CONTROL_CLASS, 'bg-surface')}
                >
                    <option value="" disabled>
                        {placeholder}
                    </option>
                    {options.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.label}
                        </option>
                    ))}
                </select>
                {description !== undefined && (
                    <span
                        id={`${id}-d`}
                        className="mt-1 block text-sm text-text-muted"
                    >
                        {description}
                    </span>
                )}
                {hasError && (
                    <span
                        id={`${id}-e`}
                        className="mt-1 flex items-start gap-1.5 text-sm font-medium text-danger"
                    >
                        <CircleAlertIcon
                            aria-hidden="true"
                            className="mt-0.5 size-4 shrink-0"
                        />
                        <span>{error}</span>
                    </span>
                )}
            </div>
        );
    }

    return (
        <AriaSelect
            {...props}
            placeholder={placeholder}
            value={value}
            defaultValue={defaultValue}
            onChange={(key: Key | null) => {
                if (key !== null) {
                    onChange?.(String(key));
                }
            }}
            isInvalid={error !== undefined && error !== ''}
            onBlur={blurAnnounce.onBlur}
            onFocus={blurAnnounce.onFocus}
            validationBehavior="aria"
            className={cn('block w-full', className)}
        >
            <FieldLabel isOptional={isOptional}>{label}</FieldLabel>
            <AriaButton
                className={cn(
                    CONTROL_CLASS,
                    'flex items-center justify-between gap-2 text-left',
                )}
            >
                <SelectValue className="data-[placeholder]:text-text-muted" />
                <ChevronDownIcon
                    aria-hidden="true"
                    className="size-5 shrink-0"
                />
            </AriaButton>
            {description !== undefined && <FieldHelp>{description}</FieldHelp>}
            <FieldErrorText>{error}</FieldErrorText>
            <Popover className="max-h-[calc(100dvh-32px)] min-w-(--trigger-width) overflow-auto rounded-md border-2 border-border bg-surface shadow-(--shadow)">
                <ListBox className="p-1">
                    {options.map((option) => (
                        <ListBoxItem
                            key={option.id}
                            id={option.id}
                            textValue={option.label}
                            className="group flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-sm px-3 text-text data-[focused]:bg-brand data-[focused]:text-on-brand data-[selected]:font-bold forced-colors:data-[focused]:bg-[Highlight] forced-colors:data-[focused]:text-[HighlightText]"
                        >
                            {option.label}
                            <CheckIcon
                                aria-hidden="true"
                                className="size-4 shrink-0 opacity-0 group-data-[selected]:opacity-100"
                            />
                        </ListBoxItem>
                    ))}
                </ListBox>
            </Popover>
        </AriaSelect>
    );
}
