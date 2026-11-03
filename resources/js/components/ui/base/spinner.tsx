import { Loader2Icon } from 'lucide-react';
import { cn } from '@/lib/utils';

/** Decorative spinner; the text next to it carries the meaning. Static under reduced motion. */
export function PendingSpinner({ className }: { className?: string }) {
    return (
        <Loader2Icon
            aria-hidden="true"
            className={cn(
                'size-4 animate-spin motion-reduce:animate-none',
                className,
            )}
        />
    );
}
