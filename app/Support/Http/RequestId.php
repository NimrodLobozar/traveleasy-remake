<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * The reference of one request (TE-019 AC3): shown on error pages, sent as `X-Request-Id` and added to every log line.
 *
 * 128 random bits as 32 hex characters: not sequential and not derived from the user, the IP or the time. An incoming
 * `X-Request-Id` header is ignored, so a client can't plant a value in the logs or on the page.
 */
final class RequestId
{
    public const string HEADER = 'X-Request-Id';

    /** Key in Laravel's log Context: every log line carries it under `extra.request_id`. */
    public const string CONTEXT_KEY = 'request_id';

    private const string ATTRIBUTE = 'request_id';

    /**
     * The request's ID, made on first use.
     */
    public static function of(Request $request): string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($id)) {
            return $id;
        }

        $id = bin2hex(random_bytes(16));
        $request->attributes->set(self::ATTRIBUTE, $id);

        return $id;
    }
}
