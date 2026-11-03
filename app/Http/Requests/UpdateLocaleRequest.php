<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Locale;
use App\Http\Controllers\LocaleController;
use App\Support\Http\SameOriginReferrer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The language switch (TE-017 AC3): only a locale on the allow-list is accepted.
 */
final class UpdateLocaleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::enum(Locale::class)],
        ];
    }

    /**
     * One localised message for every failure; the submitted value is never echoed.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = __('shell.lang.invalid');

        return [
            'locale.required' => $message,
            'locale.string' => $message,
            'locale.enum' => $message,
        ];
    }

    public function locale(): Locale
    {
        return Locale::from($this->string('locale')->toString());
    }

    /**
     * After a failed validation go back to the same-origin page, never to an external Referer (Laravel's default
     * `url()->previous()` follows the header as-is).
     */
    protected function getRedirectUrl(): string
    {
        return url(app(SameOriginReferrer::class)->path($this, excludedPaths: [LocaleController::PATH]));
    }
}
