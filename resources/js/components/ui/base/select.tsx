import { CheckIcon, ChevronDownIcon } from 'lucide-react';
import type { Key, ReactNode } from 'react';
import {
    Button as AriaButton,
    ListBox,
    ListBoxItem,
    Popover,
    Select as AriaSelect,
    SelectValue,
} from 'react-aria-components';
import { cn } from '@/lib/utils';
import {
    CONTROL_CLASS,
    FieldErrorText,
    FieldHelp,
    FieldLabel,
} from './field-parts';

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
