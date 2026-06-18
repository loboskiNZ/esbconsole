<?php

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddEffectToPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'effect_id' => ['required', 'integer', 'exists:effects,id'],
            'preferred_slot_number' => ['nullable', 'integer', 'min:1', 'max:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'effect_id.required' => 'Select an X32 effect to add to this package.',
            'effect_id.exists' => 'Selected effect is not valid.',
        ];
    }
}
