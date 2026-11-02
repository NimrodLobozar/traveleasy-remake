import { Loader2Icon } from 'lucide-react';

import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

function Spinner({ className, ...props }: React.ComponentProps<'svg'>) {
    const { t } = useTranslation();

    return (
        <Loader2Icon
            // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role -- an SVG icon can't be an <output>
            role="status"
            aria-label={t('common.loading')}
            className={cn('size-4 animate-spin', className)}
            {...props}
        />
    );
}

export { Spinner };
