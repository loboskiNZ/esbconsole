<?php

namespace App\Http\Requests;

use App\Support\InstrumentCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->person_id !== null;
    }

    protected function prepareForValidation(): void
    {
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
            'stage_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telephone' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'primary_instrument' => ['required', 'string', Rule::in(InstrumentCatalog::slugs())],
            'additional_instruments' => ['nullable', 'array'],
            'additional_instruments.*' => ['string', 'distinct', Rule::in(InstrumentCatalog::slugs())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        return $this->validated();
    }
}
