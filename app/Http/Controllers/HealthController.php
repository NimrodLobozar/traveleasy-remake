<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Health\HealthCheckFailed;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Response;
use Throwable;

/**
 * `/up`, the container health check (TE-012 AC5, TE-028 AC3).
 *
 * Replaces the framework's health page, which links fonts.bunny.net and jsdelivr (a preconnect leaks the visitor's
 * IP). The body is plain text without any URL: `OK` with 200 when every DiagnosingHealth listener passes (the database
 * check among them), a generic message with 500 otherwise. Registered outside the `web` group: no session, no cookies.
 */
final class HealthController
{
    public function __invoke(Dispatcher $events): Response
    {
        try {
            $events->dispatch(new DiagnosingHealth);
        } catch (HealthCheckFailed) {
            // Already logged by the failing check, without connection details.
            return $this->plain('Unavailable', 500);
        } catch (Throwable $e) {
            report($e);

            return $this->plain('Unavailable', 500);
        }

        return $this->plain('OK', 200);
    }

    private function plain(string $body, int $status): Response
    {
        return new Response($body, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
