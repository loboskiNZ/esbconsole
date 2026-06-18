<?php

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsoleEffectPackageRequest extends FormRequest
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
            'effect_package_type_id' => [
                'required',
                'integer',
                Rule::exists('effect_package_types', 'id')->where('is_active', true),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
