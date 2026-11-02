import { CheckIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

// A native checkbox, not Radix's: Radix renders a hidden "bubble" input with an inline style
// attribute, which the CSP (style-src without 'unsafe-inline', TE-015) blocks in server-rendered
// HTML. The native control also submits with its form without JavaScript. In forced-colors mode
// the browser's own checkbox is shown.
function Checkbox({
    className,
    ...props
}: Omit<React.ComponentProps<'input'>, 'type'>) {
    return (
        <span className="relative inline-flex size-4 shrink-0">
            <input
                type="checkbox"
                data-slot="checkbox"
                className={cn(
                    'peer size-4 shrink-0 appearance-none rounded-[4px] border border-input bg-transparent shadow-xs transition-shadow checked:border-primary checked:bg-primary focus-visible:border-ring disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 forced-colors:appearance-auto',
                    className,
                )}
                {...props}
            />
            <CheckIcon
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 m-auto size-3.5 text-primary-foreground opacity-0 peer-checked:opacity-100 forced-colors:hidden"
            />
        </span>
    );
}

export { Checkbox };
