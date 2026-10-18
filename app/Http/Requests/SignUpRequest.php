<?php

namespace App\Http\Requests;

use App\Support\DutchDate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                    $fail('Dit e-mailadres lijkt onvolledig. Controleer het deel na de @ (bijv. naam@voorbeeld.nl).');
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
                    $fail('Vul een geldige datum in, bijvoorbeeld 10-09-1992.');
                } elseif ($date->isFuture() || $date->year < 1900) {
                    $fail('Controleer uw geboortedatum.');
                }
            }],
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'voornaam',
            'middle_name' => 'tussenvoegsel',
            'last_name' => 'achternaam',
            'email' => 'e-mailadres',
            'phone_mobile' => 'mobiel nummer',
            'phone_home' => 'telefoonnummer',
            'street' => 'straat en huisnummer',
            'postal_code' => 'postcode',
            'city' => 'woonplaats',
            'country' => 'land',
            'birthday' => 'geboortedatum',
            'club_id' => 'vereniging',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'U moet akkoord gaan met het gebruik van uw gegevens voor de ledenadministratie.',
        ];
    }

    /**
     * The member attributes, ready for CreateMember.
     *
     * @return array<string, mixed>
     */
    public function memberAttributes(): array
    {
        $data = $this->safe()->except(['consent', 'birthday']);

        return [
            ...array_map(fn ($value) => is_string($value) ? (trim($value) ?: null) : $value, $data),
            'birthday' => DutchDate::parse($this->input('birthday'))?->toDateString(),
            'joined_at' => today()->toDateString(),
        ];
    }
}
