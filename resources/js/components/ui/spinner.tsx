import { Loader2Icon } from 'lucide-react';

import { cn } from '@/lib/utils';

function Spinner({ className, ...props }: React.ComponentProps<'svg'>) {
    return (
        <Loader2Icon
            // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role -- an SVG icon can't be an <output>
            role="status"
            aria-label="Loading"
            className={cn('size-4 animate-spin', className)}
            {...props}
        />
    );
}

export { Spinner };
