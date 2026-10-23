<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Contact details a member changes in the ledenportaal (same rules as the sign-up form).
 */
class UpdateContactDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,filter', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (! preg_match('/@[^@\s]+\.[a-z]{2,}$/i', (string) $value)) {
                    $fail(__('portal.edit.email_incomplete'));
                }
            }],
            'phone_mobile' => ['nullable', 'string', 'max:30'],
            'phone_home' => ['nullable', 'string', 'max:30'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['email', 'phone_mobile', 'phone_home', 'street', 'postal_code', 'city', 'country'])
            ->mapWithKeys(fn (string $field) => [$field => mb_strtolower(__("portal.fields.{$field}"))])
            ->all();
    }
}
