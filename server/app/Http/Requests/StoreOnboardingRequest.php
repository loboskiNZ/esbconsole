<?php

namespace App\Http\Requests;

use App\Support\InstrumentCatalog;
use App\Support\OnboardingHumanCheck;
use App\Support\PortalUsername;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => PortalUsername::normalize((string) $this->input('username')),
            ]);
        }

        $primary = (string) $this->input('primary_instrument', '');
        $additional = $this->input('additional_instruments', []);

        if ($primary !== '' && is_array($additional) && in_array($primary, $additional, true)) {
            $this->merge([
                'additional_instruments' => array_values(array_filter(
                    $additional,
                    fn ($slug) => $slug !== $primary,
                )),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-z0-9]+$/',
                'unique:users,username',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:50',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
            'password_confirm' => ['required', 'string', 'same:password'],
            'human_answer' => ['required', 'integer'],
            'honeypot' => ['nullable', 'string', 'max:0'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'stage_name' => ['required', 'string', 'max:255'],
            'primary_instrument' => ['required', 'string', Rule::in(InstrumentCatalog::slugs())],
            'additional_instruments' => ['nullable', 'array'],
            'additional_instruments.*' => ['string', 'distinct', Rule::in(InstrumentCatalog::slugs())],
            'email' => ['required', 'email', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'country_iso3' => ['required', 'string', 'size:3'],
            'city' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $token = (string) $this->route('token');

            if (! OnboardingHumanCheck::validate($token, $this->input('human_answer'))) {
                $validator->errors()->add(
                    'human_answer',
                    'That answer did not match. Refresh the page and try again.',
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        return $this->validated();
    }
}
