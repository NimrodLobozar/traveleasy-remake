import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/i18n';
import type { Translator } from '@/lib/i18n';

type Props = {
    onSuccess: () => void;
};

/** Suggests a device name from the user agent. Browser-only: call it from an event handler. */
function guessDeviceName(t: Translator): string {
    const ua = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    if (browser && os) {
        return t('passkey.device_name', { browser, os });
    }

    return browser ?? os ?? '';
}

export default function PasskeyRegistration({ onSuccess }: Props) {
    const [name, setName] = useState('');
    const { t } = useTranslation();

    const [showForm, setShowForm] = useState(false);
    const { register, isLoading, error, isSupported } = usePasskeyRegister({
        onSuccess: () => {
            setName('');
            setShowForm(false);
            onSuccess();
        },
    });

    const handleSubmit = async (e: React.SubmitEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (!name.trim()) {
            return;
        }

        await register(name);
    };

    const handleCancel = () => {
        setShowForm(false);
        setName('');
    };

    if (!isSupported) {
        return (
            <div className="text-sm text-muted-foreground">
                {t('passkey.unsupported')}
            </div>
        );
    }

    if (!showForm) {
        return (
            <Button
                variant="outline"
                onClick={() => {
                    setName(guessDeviceName(t));
                    setShowForm(true);
                }}
            >
                {t('passkey.add')}
            </Button>
        );
    }

    return (
        <form
            onSubmit={(e) => {
                void handleSubmit(e);
            }}
            className="space-y-4 rounded-lg border border-border bg-muted/50 p-4"
        >
            <div className="grid gap-2">
                <Label htmlFor="passkey-name">{t('passkey.name.label')}</Label>
                <Input
                    id="passkey-name"
                    type="text"
                    value={name}
                    onChange={(e) => {
                        setName(e.target.value);
                    }}
                    placeholder={t('passkey.name.placeholder')}
                    className="mt-1 block w-full border-foreground/20"
                    // oxlint-disable-next-line jsx-a11y/no-autofocus -- intended focus management: the name field is the only control of this dialog step
                    autoFocus
                />
                <p className="text-xs text-muted-foreground">
                    {t('passkey.name.hint')}
                </p>
            </div>

            {error && <InputError message={error} />}

            <div className="flex gap-2">
                <Button type="submit" disabled={isLoading || !name.trim()}>
                    {isLoading
                        ? t('passkey.registering')
                        : t('passkey.register')}
                </Button>
                <Button type="button" variant="ghost" onClick={handleCancel}>
                    {t('passkey.cancel')}
                </Button>
            </div>
        </form>
    );
}
