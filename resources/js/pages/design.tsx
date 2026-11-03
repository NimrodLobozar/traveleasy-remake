import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Alert } from '@/components/ui/base/alert';
import { ValidationAnnouncer } from '@/components/ui/base/announcer';
import { Banner, BannerLiveRegion } from '@/components/ui/base/banner';
import { Button } from '@/components/ui/base/button';
import { FormErrorSummary } from '@/components/ui/base/form-error-summary';
import { Link } from '@/components/ui/base/link';
import { NumberField } from '@/components/ui/base/number-field';
import { Select } from '@/components/ui/base/select';
import { TextField } from '@/components/ui/base/text-field';
import { ComplexShowcase } from '@/components/design/complex-showcase';

/**
 * Component showcase (TE-022). Registered only in the local and testing environments
 * (routes/testing.php), so axe and Playwright can scan every component and state. Dev copy:
 * deliberately not translated.
 */
export default function Design() {
    const [signal, setSignal] = useState(0);
    const [city, setCity] = useState('');
    const [cityError, setCityError] = useState<string | undefined>();

    return (
        <>
            <Head title="Design" />
            <div className="mx-auto flex max-w-2xl flex-col gap-8 p-4">
                <h1 className="text-3xl font-bold">Base components</h1>

                <BannerLiveRegion />
                <ValidationAnnouncer />
                <Banner label="Maintenance notice" dismissible>
                    Maintenance on Saturday night.
                </Banner>
                <Banner label="Session" severity="warning">
                    Your session expires soon.
                </Banner>

                <section
                    aria-labelledby="d-buttons"
                    className="flex flex-col gap-3"
                >
                    <h2 id="d-buttons" className="text-xl font-bold">
                        Buttons
                    </h2>
                    <div className="flex flex-wrap gap-3">
                        <Button>Primary</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="danger">Danger</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button size="sm">Small</Button>
                        <Button size="lg">Large</Button>
                        <Button isDisabled>Disabled</Button>
                        <Button isPending>Pending</Button>
                    </div>
                    <p>
                        <Link href="/">Back to the home page</Link>{' '}
                        <Link href="https://example.org">
                            Read the example terms
                        </Link>
                    </p>
                </section>

                <section
                    aria-labelledby="d-fields"
                    className="flex flex-col gap-4"
                >
                    <h2 id="d-fields" className="text-xl font-bold">
                        Fields
                    </h2>
                    <FormErrorSummary
                        focusSignal={signal}
                        errors={[
                            {
                                fieldId: 'd-email',
                                label: 'Email address',
                                message: 'Enter a valid email address.',
                            },
                        ]}
                    />
                    <Button
                        variant="secondary"
                        onPress={() => {
                            setSignal((n) => n + 1);
                        }}
                    >
                        Show error summary
                    </Button>
                    <TextField
                        id="d-email"
                        label="Email address"
                        type="email"
                        autoComplete="email"
                        description="We never send email."
                        error="Enter a valid email address."
                    />
                    <TextField
                        label="City (blur-validated)"
                        autoComplete="off"
                        value={city}
                        error={cityError}
                        onChange={(value) => {
                            setCity(value);
                            setCityError(undefined);
                        }}
                        onBlur={() => {
                            setCityError(
                                city === '' ? 'Enter a city.' : undefined,
                            );
                        }}
                    />
                    <TextField
                        label="Password"
                        type="password"
                        autoComplete="new-password"
                        description="At least 12 characters."
                    />
                    <TextField
                        label="Note"
                        type="textarea"
                        autoComplete="off"
                        isOptional
                    />
                    <TextField label="Disabled" autoComplete="off" isDisabled />
                    <Select
                        label="Travel class"
                        placeholder="Choose"
                        options={[
                            { id: 'eco', label: 'Economy' },
                            { id: 'biz', label: 'Business' },
                        ]}
                    />
                    <NumberField
                        label="Adults"
                        minValue={1}
                        maxValue={9}
                        defaultValue={1}
                        decrementLabel="Fewer adults"
                        incrementLabel="More adults"
                    />
                </section>

                <section
                    aria-labelledby="d-alerts"
                    className="flex flex-col gap-3"
                >
                    <h2 id="d-alerts" className="text-xl font-bold">
                        Alerts
                    </h2>
                    <Alert title="Info" dismissible>
                        An informational message.
                    </Alert>
                    <Alert severity="success" title="Saved" />
                    <Alert severity="warning" title="Careful">
                        A warning.
                    </Alert>
                    <Alert severity="danger" title="Failed">
                        Something went wrong.
                    </Alert>
                </section>

                <ComplexShowcase />
            </div>
        </>
    );
}
