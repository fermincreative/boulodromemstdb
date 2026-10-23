<?php

namespace App\Http\Requests;

use App\Models\Club;
use App\Support\DutchDate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation of the public sign-up form.
 */
class SignUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,filter', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (! preg_match('/@[^@\s]+\.[a-z]{2,}$/i', (string) $value)) {
                    $fail(__('signup.validation.email_incomplete'));
                }
            }],
            'phone_mobile' => ['nullable', 'string', 'max:30'],
            'phone_home' => ['nullable', 'string', 'max:30'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'birthday' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                $date = DutchDate::parse((string) $value);

                if (! $date) {
                    $fail(__('signup.validation.birthday_invalid'));
                } elseif ($date->isFuture() || $date->year < 1900) {
                    $fail(__('signup.validation.birthday_check'));
                }
            }],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['first_name', 'middle_name', 'last_name', 'email', 'phone_mobile', 'phone_home', 'street', 'postal_code', 'city', 'country', 'birthday'])
            ->mapWithKeys(fn (string $field) => [$field => mb_strtolower(__("signup.fields.{$field}"))])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => __('signup.validation.consent'),
        ];
    }

    /**
     * The member attributes, ready for CreateMember. Everyone signing up here joins the sign-up club (SBM Halpas);
     * members of the other clubs are registered through their own club.
     *
     * @return array<string, mixed>
     */
    public function memberAttributes(): array
    {
        $data = $this->safe()->except(['consent', 'birthday']);

        return [
            ...array_map(fn ($value) => is_string($value) ? (trim($value) ?: null) : $value, $data),
            'birthday' => DutchDate::parse($this->input('birthday'))?->toDateString(),
            'club_id' => Club::signUpClub()?->id,
            'joined_at' => today()->toDateString(),
        ];
    }
}
