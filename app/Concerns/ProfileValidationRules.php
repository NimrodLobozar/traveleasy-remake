<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Rules\UniqueEmail;
use App\Support\EmailAddress;
use Illuminate\Contracts\Validation\ValidationRule;
use Stringable;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|Stringable|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|Stringable|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * Expects normalised input ({@see EmailAddress}); uniqueness is case-insensitive and the
     * protected super_admin address counts as taken (TE-049).
     *
     * @return array<int, ValidationRule|Stringable|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            new UniqueEmail($userId),
        ];
    }
}
