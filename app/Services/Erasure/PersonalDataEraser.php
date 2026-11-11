<?php

declare(strict_types=1);

namespace App\Services\Erasure;

use App\Models\User;
use App\Services\AccountEraser;

/**
 * Erases (or anonymises) one table's personal data of a user. Implementations are registered with the container tag
 * {@see AccountEraser::ERASER_TAG} and run inside the erasure transaction, before the user row is
 * deleted. Throwing rolls back the whole erasure of that user.
 */
interface PersonalDataEraser
{
    public function erase(User $user): void;
}
