import { Link } from '@inertiajs/react';
import { home } from '@/routes';

/** Wordmark "Travel" + "Easy" with the route-arc mark (design system section 1). The name is a brand, not translated. */
export default function Brand() {
    return (
        <Link
            href={home()}
            className="inline-flex min-h-11 items-center gap-2 text-2xl text-brand"
        >
            <svg
                aria-hidden="true"
                focusable="false"
                width="28"
                height="28"
                viewBox="0 0 28 28"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            >
                <path d="M3 22 C 8 6, 18 6, 22 16" />
                <circle
                    cx="23"
                    cy="19"
                    r="2.5"
                    fill="currentColor"
                    stroke="none"
                />
            </svg>
            <span className="text-text">
                <span className="font-medium">Travel</span>
                <span className="font-extrabold">Easy</span>
            </span>
        </Link>
    );
}
