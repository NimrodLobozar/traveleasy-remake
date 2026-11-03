import { usePage } from '@inertiajs/react';
import { ChevronDown, Moon, Sun } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Native radios in a fieldset: the browser gives the radiogroup role, arrow-key movement and
 * selection for free (TE-018 AC4). Each instance gets its own `name`, so the desktop and the
 * mobile copy (one of them is always display:none) never share a group.
 */
function ThemeRadios({
    visibleLegend,
    inputRef,
}: {
    visibleLegend: boolean;
    inputRef?: (value: Appearance, element: HTMLInputElement | null) => void;
}) {
    const { t } = useTranslation();
    const { appearance, updateAppearance } = useAppearance(
        usePage().props.appearance,
    );
    const name = useId();
    const noteId = useId();
    const options: { value: Appearance; label: string }[] = [
        { value: 'system', label: t('shell.theme.system') },
        { value: 'light', label: t('shell.theme.light') },
        { value: 'dark', label: t('shell.theme.dark') },
    ];

    return (
        <fieldset aria-describedby={noteId} className="min-w-0">
            <legend
                className={cn(
                    visibleLegend ? 'px-3 pb-1 font-bold' : 'sr-only',
                )}
            >
                {t('shell.theme.label')}
            </legend>
            <div className="flex flex-col">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-3 font-medium text-text',
                            appearance === option.value && 'bg-surface-2',
                        )}
                    >
                        <input
                            ref={(element) => {
                                inputRef?.(option.value, element);
                            }}
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={appearance === option.value}
                            onChange={() => {
                                updateAppearance(option.value);
                            }}
                            className="size-5 accent-brand"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
            <p id={noteId} className="px-3 pt-1 pb-2 text-sm text-text-muted">
                {t('shell.theme.note')}
            </p>
        </fieldset>
    );
}

/**
 * Theme control (TE-018). From md: a "Thema" disclosure button opening a popover with the three
 * options; Escape closes it and returns focus to the button, as does a click outside. Below md:
 * the same options inline in the menu panel.
 */
export default function ThemeSwitch() {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const popoverId = useId();
    const root = useRef<HTMLDivElement>(null);
    const button = useRef<HTMLButtonElement>(null);
    const radios = useRef(new Map<Appearance, HTMLInputElement>());
    const { appearance } = useAppearance(usePage().props.appearance);

    useEffect(() => {
        if (open) {
            radios.current.get(appearance)?.focus();
        }
        // Only when it opens; selecting must not re-steal focus.
        // oxlint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                setOpen(false);
                button.current?.focus();
            }
        };
        // Tabbing out of the popover closes it; focus goes where the user sent it.
        const onFocusOut = (event: FocusEvent) => {
            if (
                event.relatedTarget instanceof Node &&
                !root.current?.contains(event.relatedTarget)
            ) {
                setOpen(false);
            }
        };
        const element = root.current;
        element?.addEventListener('keydown', onKeyDown);
        element?.addEventListener('focusout', onFocusOut);
        const onPointerDown = (event: PointerEvent) => {
            if (
                event.target instanceof Node &&
                !root.current?.contains(event.target)
            ) {
                setOpen(false);
            }
        };
        document.addEventListener('pointerdown', onPointerDown);

        return () => {
            element?.removeEventListener('keydown', onKeyDown);
            element?.removeEventListener('focusout', onFocusOut);
            document.removeEventListener('pointerdown', onPointerDown);
        };
    }, [open]);

    return (
        <>
            <div ref={root} className="relative hidden md:block">
                <button
                    ref={button}
                    type="button"
                    aria-expanded={open}
                    aria-controls={popoverId}
                    onClick={() => {
                        setOpen(!open);
                    }}
                    className="inline-flex min-h-11 items-center gap-2 rounded-md border-2 border-border px-3 font-bold text-text"
                >
                    <Sun
                        aria-hidden="true"
                        focusable="false"
                        className="size-5 dark:hidden"
                    />
                    <Moon
                        aria-hidden="true"
                        focusable="false"
                        className="hidden size-5 dark:block"
                    />
                    {t('shell.theme.label')}
                    <ChevronDown
                        aria-hidden="true"
                        focusable="false"
                        className="size-4"
                    />
                </button>
                <div
                    id={popoverId}
                    hidden={!open}
                    className="absolute top-full right-0 z-20 mt-2 w-[260px] rounded-xl border-2 border-border bg-surface p-2 shadow-card"
                >
                    <ThemeRadios
                        visibleLegend={false}
                        inputRef={(value, element) => {
                            if (element) {
                                radios.current.set(value, element);
                            } else {
                                radios.current.delete(value);
                            }
                        }}
                    />
                </div>
            </div>
            <div className="md:hidden">
                <ThemeRadios visibleLegend />
            </div>
        </>
    );
}
