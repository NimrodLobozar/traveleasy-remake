import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { SyntheticEvent } from 'react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Field = 'from' | 'to' | 'date' | 'travellers';
const FIELDS: readonly Field[] = ['from', 'to', 'date', 'travellers'];

const inputClass =
    'min-h-12 w-full rounded-md border-2 border-border bg-surface px-3 text-text aria-invalid:border-danger';

function isInvalid(field: Field, raw: string): boolean {
    const value = raw.trim();
    if (field === 'travellers') {
        const count = Number(value);

        return !Number.isInteger(count) || count < 1 || count > 9;
    }

    return value === '';
}

/** Public home (TE-016 placeholder): the search itself is built in M3; this page proves the shell. */
export default function Home() {
    const { t } = useTranslation();
    const [errors, setErrors] = useState<Partial<Record<Field, string>>>({});
    const summary = useRef<HTMLElement>(null);
    const failed = FIELDS.filter((field) => errors[field] !== undefined);

    const messages: Record<Field, string> = {
        from: t('search.error.from'),
        to: t('search.error.to'),
        date: t('search.error.date'),
        travellers: t('search.error.travellers'),
    };

    const onChange = (field: Field, value: string) => {
        if (errors[field] !== undefined && !isInvalid(field, value)) {
            const { [field]: _fixed, ...rest } = errors;
            setErrors(rest);
        }
    };

    const onSubmit = (event: SyntheticEvent<HTMLFormElement, SubmitEvent>) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const read = (field: Field) => {
            const value = data.get(field);

            return typeof value === 'string' ? value : '';
        };
        const next: Partial<Record<Field, string>> = {};
        for (const field of FIELDS) {
            if (isInvalid(field, read(field))) {
                next[field] = messages[field];
            }
        }
        setErrors(next);
        if (Object.keys(next).length > 0) {
            // The summary mounts in this render; focus it after the commit.
            setTimeout(() => summary.current?.focus(), 0);

            return;
        }
        router.get(
            '/search',
            Object.fromEntries(FIELDS.map((field) => [field, read(field)])),
        );
    };

    const labels: Record<Field, string> = {
        from: t('search.from'),
        to: t('search.to'),
        date: t('search.date'),
        travellers: t('search.travellers'),
    };

    const points = [
        {
            id: 'price',
            title: t('home.value.price.title'),
            body: t('home.value.price.body'),
        },
        {
            id: 'group',
            title: t('home.value.group.title'),
            body: t('home.value.group.body'),
        },
        {
            id: 'data',
            title: t('home.value.data.title'),
            body: t('home.value.data.body'),
        },
    ];

    return (
        <>
            <Head title={t('home.title')} />
            <div className="mx-auto max-w-7xl px-4 py-10 md:px-8 md:py-16">
                <div className="grid items-start gap-10 lg:grid-cols-[1fr_440px]">
                    <div>
                        <h1 className="text-4xl font-extrabold text-balance">
                            {t('home.title')}
                        </h1>
                        <p className="mt-4 max-w-[56ch] text-lg text-text-muted">
                            {t('home.lead')}
                        </p>
                    </div>
                    <section
                        aria-labelledby="search-title"
                        className="rounded-xl border border-line bg-surface p-6 shadow-sm"
                    >
                        <h2 id="search-title" className="text-xl font-bold">
                            {t('home.search.title')}
                        </h2>
                        <p className="mt-1 text-sm text-text-muted">
                            {t('search.required')}
                        </p>
                        <form
                            noValidate
                            onSubmit={onSubmit}
                            className="mt-4 flex flex-col gap-4"
                        >
                            {failed.length > 0 && (
                                <section
                                    ref={summary}
                                    tabIndex={-1}
                                    aria-labelledby="search-errors"
                                    className="rounded-md border-2 border-danger p-3"
                                >
                                    <h3
                                        id="search-errors"
                                        className="font-bold text-danger"
                                    >
                                        {t('search.error.title')}
                                    </h3>
                                    <ul className="mt-1 list-disc pl-5">
                                        {failed.map((field) => (
                                            <li key={field}>
                                                <a
                                                    href={`#search-${field}`}
                                                    className="text-brand underline underline-offset-4"
                                                >
                                                    {labels[field]}:{' '}
                                                    {errors[field]}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            )}
                            {FIELDS.map((field) => (
                                <div
                                    key={field}
                                    className="flex flex-col gap-1"
                                >
                                    <label
                                        htmlFor={`search-${field}`}
                                        className="font-bold"
                                    >
                                        {labels[field]}
                                    </label>
                                    <input
                                        id={`search-${field}`}
                                        name={field}
                                        type={
                                            field === 'travellers'
                                                ? 'number'
                                                : 'text'
                                        }
                                        {...(field === 'travellers'
                                            ? {
                                                  min: 1,
                                                  max: 9,
                                                  inputMode: 'numeric',
                                              }
                                            : {})}
                                        defaultValue={
                                            field === 'travellers'
                                                ? '1'
                                                : undefined
                                        }
                                        autoComplete="off"
                                        required
                                        onChange={(event) => {
                                            onChange(field, event.target.value);
                                        }}
                                        aria-invalid={
                                            errors[field] !== undefined
                                        }
                                        aria-describedby={
                                            [
                                                field === 'date'
                                                    ? 'search-date-hint'
                                                    : null,
                                                errors[field] !== undefined
                                                    ? `search-${field}-error`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' ') || undefined
                                        }
                                        className={inputClass}
                                    />
                                    {field === 'date' && (
                                        <p
                                            id="search-date-hint"
                                            className="text-sm text-text-muted"
                                        >
                                            {t('search.date_hint')}
                                        </p>
                                    )}
                                    {errors[field] !== undefined && (
                                        <p
                                            id={`search-${field}-error`}
                                            className={cn(
                                                'text-sm font-medium text-danger',
                                            )}
                                        >
                                            {errors[field]}
                                        </p>
                                    )}
                                </div>
                            ))}
                            <button
                                type="submit"
                                className="min-h-12 rounded-md border-2 border-brand bg-brand px-5 font-bold text-on-brand hover:bg-brand-hover"
                            >
                                {t('search.submit')}
                            </button>
                        </form>
                    </section>
                </div>
                <ul className="mt-12 grid gap-8 md:grid-cols-3">
                    {points.map((point) => (
                        <li key={point.id}>
                            <h2 className="text-xl font-bold">{point.title}</h2>
                            <p className="mt-1 max-w-[65ch] text-text-muted">
                                {point.body}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
