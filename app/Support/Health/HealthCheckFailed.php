<?php

declare(strict_types=1);

namespace App\Support\Health;

use RuntimeException;

/**
 * Thrown from a DiagnosingHealth listener to make `/up` answer 500 (TE-012 AC5).
 *
 * The message is generic on purpose: the cause is logged by the check itself, without connection details, and the
 * exception is not reported again (bootstrap/app.php), so a down database logs one line per probe, not a stack trace.
 */
final class HealthCheckFailed extends RuntimeException {}
