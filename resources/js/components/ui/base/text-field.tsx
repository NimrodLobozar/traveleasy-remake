import { EyeIcon, EyeOffIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    Button as AriaButton,
    Input,
    TextArea,
    TextField as AriaTextField,
} from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    CONTROL_CLASS,
    FieldErrorText,
    FieldHelp,
    FieldLabel,
} from './field-parts';

/** The autocomplete tokens the app uses (WCAG 1.3.5); extend deliberately. */
export type AutoComplete =
    | 'given-name'
    | 'family-name'
    | 'name'
    | 'email'
    | 'tel'
    | 'bday'
    | 'new-password'
    | 'current-password'
    | 'one-time-code'
    | 'street-address'
    | 'postal-code'
    | 'country-name'
    | 'off';

export type TextFieldProps = {
    label: string;
    autoComplete: AutoComplete;
    name?: string;
    id?: string;
    type?:
        | 'text'
        | 'email'
        | 'tel'
        | 'search'
        | 'url'
        | 'password'
        | 'textarea';
    description?: ReactNode;
    /** Server- or client-side error text; makes the field invalid (aria-invalid) and is linked by aria-describedby. */
    error?: string;
    isOptional?: boolean;
    isRequired?: boolean;
    isDisabled?: boolean;
    isReadOnly?: boolean;
    value?: string;
    defaultValue?: string;
    onChange?: (value: string) => void;
    onBlur?: () => void;
    placeholder?: string;
    rows?: number;
    inputMode?: 'text' | 'email' | 'tel' | 'numeric' | 'search' | 'url';
    className?: string;
};

/**
 * TextField, password and textarea (TE-022, system.md 4.3). Label, help and error are wired by RAC
 * (`aria-labelledby`, `aria-describedby`, `aria-invalid`); the password toggle has a fixed name and
 * `aria-pressed`. Paste is never blocked.
 */
export function TextField({
    label,
    description,
    error,
    isOptional,
    type = 'text',
    rows = 4,
    inputMode,
    placeholder,
    className,
    ...props
}: TextFieldProps) {
    const { t } = useTranslation();
    const [revealed, setRevealed] = useState(false);
    const isPassword = type === 'password';
    const isTextarea = type === 'textarea';
    const inputType = isTextarea
        ? 'text'
        : isPassword && revealed
          ? 'text'
          : type;

    return (
        <AriaTextField
            {...props}
            type={inputType}
            isInvalid={error !== undefined && error !== ''}
            validationBehavior="aria"
            className={cn('block w-full', className)}
        >
            <FieldLabel isOptional={isOptional}>{label}</FieldLabel>
            {isTextarea ? (
                <TextArea
                    rows={rows}
                    placeholder={placeholder}
                    className={cn(CONTROL_CLASS, 'py-2')}
                />
            ) : isPassword ? (
                <div className="relative">
                    <Input
                        placeholder={placeholder}
                        className={cn(CONTROL_CLASS, 'pr-14')}
                    />
                    <AriaButton
                        aria-pressed={revealed}
                        onPress={() => {
                            setRevealed((value) => !value);
                        }}
                        className="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center rounded-md px-3 text-text hover:bg-surface-2"
                    >
                        {revealed ? (
                            <EyeOffIcon aria-hidden="true" className="size-5" />
                        ) : (
                            <EyeIcon aria-hidden="true" className="size-5" />
                        )}
                        <span className="sr-only">{t('ui.password.show')}</span>
                    </AriaButton>
                </div>
            ) : (
                <Input
                    inputMode={inputMode}
                    placeholder={placeholder}
                    className={CONTROL_CLASS}
                />
            )}
            {description !== undefined && <FieldHelp>{description}</FieldHelp>}
            <FieldErrorText>{error}</FieldErrorText>
        </AriaTextField>
    );
}
