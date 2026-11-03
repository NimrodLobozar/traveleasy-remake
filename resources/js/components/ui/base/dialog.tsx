import { XIcon } from 'lucide-react';
import { useId, useRef } from 'react';
import type { ReactElement, ReactNode } from 'react';
import {
    Button as AriaButton,
    Dialog as AriaDialog,
    DialogTrigger,
    Heading,
    Modal,
    ModalOverlay,
} from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Button } from './button';
import { useIsOverflowing } from './overflow';

export type DialogConfirm = {
    /** Names the action, e.g. "Boeking annuleren". */
    label: string;
    onConfirm: () => void;
    variant?: 'primary' | 'danger';
    /** While true Escape and the buttons are inert (an action is running). */
    isPending?: boolean;
    /** Keep the dialog open after pressing the button (e.g. while the server answers). */
    keepOpen?: boolean;
};

export type DialogProps = {
    /** The pressable that opens the dialog (a base `Button`); focus returns to it on close. */
    trigger: ReactElement;
    title: string;
    /** Shown under the title and linked with `aria-describedby`. State the consequence, money incl. VAT. */
    description?: ReactNode;
    children?: ReactNode;
    /** `alertdialog` for a destructive confirmation. */
    role?: 'dialog' | 'alertdialog';
    /** Adds the confirm button; the safe "Back" button gets the initial focus. */
    confirm?: DialogConfirm;
    /** Defaults to "Terug" / "Back". */
    cancelLabel?: string;
    isOpen?: boolean;
    defaultOpen?: boolean;
    onOpenChange?: (isOpen: boolean) => void;
    className?: string;
};

/** The scrolling body; keyboard-focusable and named only while it overflows (mounted when open). */
function ScrollBody({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const scrolls = useIsOverflowing(ref);

    return (
        <div
            ref={ref}
            {...(scrolls
                ? { role: 'region', tabIndex: 0, 'aria-label': title }
                : {})}
            className="min-h-0 flex-1 overflow-y-auto px-6 py-3"
        >
            {children}
        </div>
    );
}

/**
 * Dialog (TE-023, system.md 4.9) on RAC `Modal` + `Dialog`: focus is trapped, the page behind is
 * inert and does not scroll, Escape closes (unless an action is pending), focus returns to the
 * trigger. The panel is at most `100dvh - 32px` tall; the body scrolls and the title and actions
 * stay in view (400 % zoom, text spacing). With `confirm`, initial focus goes to the safe "Back"
 * button, never to the destructive one.
 */
export function Dialog({
    trigger,
    title,
    description,
    children,
    role = 'dialog',
    confirm,
    cancelLabel,
    isOpen,
    defaultOpen,
    onOpenChange,
    className,
}: DialogProps) {
    const { t } = useTranslation();
    const descriptionId = useId();
    const pending = confirm?.isPending === true;

    return (
        <DialogTrigger
            isOpen={isOpen}
            defaultOpen={defaultOpen}
            onOpenChange={onOpenChange}
        >
            {trigger}
            <ModalOverlay
                isDismissable={role === 'dialog'}
                isKeyboardDismissDisabled={pending}
                className="fixed inset-0 z-50 flex items-center justify-center bg-[rgba(7,20,26,0.62)] p-4"
            >
                <Modal className="flex max-h-[calc(100dvh-2rem)] w-full max-w-110 flex-col rounded-xl bg-surface text-text shadow-(--shadow)">
                    <AriaDialog
                        role={role}
                        aria-describedby={
                            description !== undefined
                                ? descriptionId
                                : undefined
                        }
                        className={cn('flex min-h-0 flex-col', className)}
                    >
                        {({ close }) => (
                            <>
                                <div className="flex items-start justify-between gap-3 px-6 pt-6">
                                    <Heading
                                        slot="title"
                                        className="text-xl font-bold"
                                    >
                                        {title}
                                    </Heading>
                                    <AriaButton
                                        onPress={close}
                                        isDisabled={pending}
                                        aria-label={t('ui.close')}
                                        className="-mt-2 -mr-2 flex size-11 shrink-0 items-center justify-center rounded-md hover:bg-surface-2 data-[disabled]:cursor-not-allowed data-[disabled]:text-text-muted"
                                    >
                                        <XIcon
                                            aria-hidden="true"
                                            className="size-5"
                                        />
                                    </AriaButton>
                                </div>
                                <ScrollBody title={title}>
                                    {description !== undefined && (
                                        <p id={descriptionId} className="mb-3">
                                            {description}
                                        </p>
                                    )}
                                    {children}
                                </ScrollBody>
                                {confirm !== undefined && (
                                    <div className="flex flex-col-reverse gap-3 px-6 pt-3 pb-6 sm:flex-row sm:justify-end">
                                        <Button
                                            variant="secondary"
                                            // oxlint-disable-next-line jsx-a11y/no-autofocus -- spec 4.9: initial focus on the safest action
                                            autoFocus
                                            isDisabled={pending}
                                            onPress={close}
                                            className="max-sm:w-full"
                                        >
                                            {cancelLabel ?? t('dialog.back')}
                                        </Button>
                                        <Button
                                            variant={
                                                confirm.variant ?? 'primary'
                                            }
                                            isPending={pending}
                                            onPress={() => {
                                                confirm.onConfirm();

                                                if (confirm.keepOpen !== true) {
                                                    close();
                                                }
                                            }}
                                            className="max-sm:w-full"
                                        >
                                            {confirm.label}
                                        </Button>
                                    </div>
                                )}
                            </>
                        )}
                    </AriaDialog>
                </Modal>
            </ModalOverlay>
        </DialogTrigger>
    );
}
