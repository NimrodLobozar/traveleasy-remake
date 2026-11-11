<?php

declare(strict_types=1);

namespace App\Services\Erasure;

enum ErasureOutcome: string
{
    case Erased = 'ERASED';
    case NotFound = 'NOT FOUND (already erased)';
    case Refused = 'REFUSED (protected account)';
}
