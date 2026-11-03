import ErrorPage from '@/components/error-page';

/**
 * The Inertia page the server renders for every HTML error (TE-019): the status code is kept on
 * the response, the props are `{ status, requestId, retryAfter }`.
 */
export default function Error({
    status,
    requestId,
    retryAfter,
}: {
    status: number;
    requestId: string;
    retryAfter: number | null;
}) {
    return (
        <ErrorPage
            status={status}
            requestId={requestId}
            retryAfter={retryAfter}
        />
    );
}
