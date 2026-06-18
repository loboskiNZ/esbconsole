<?php

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsoleEffectPackageRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'effect_package_type_id' => ['required', 'integer', 'exists:effect_package_types,id'],
            'effect_ids' => ['required', 'array', 'min:1'],
            'effect_ids.*' => ['integer', 'distinct', 'exists:effects,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'effect_ids.required' => 'Select at least one X32 effect for the package.',
            'effect_ids.min' => 'Select at least one X32 effect for the package.',
        ];
    }
}
