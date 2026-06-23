<?php

namespace App\Http\Requests;

use App\Support\InstrumentCatalog;
use App\Support\ProfileBio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $maxKb = (int) config('portal.profile_photo_max_kb', 5120);

        return [
            'stage_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telephone' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'primary_instrument' => ['required', 'string', Rule::in(InstrumentCatalog::slugs())],
            'additional_instruments' => ['nullable', 'array'],
            'additional_instruments.*' => ['string', 'distinct', Rule::in(InstrumentCatalog::slugs())],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (ProfileBio::exceedsLimit($this->input('bio'))) {
                $validator->errors()->add(
                    'bio',
                    'Bio must be '.ProfileBio::MAX_WORDS.' words or fewer.',
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
