<?php

namespace App\Http\Requests\Console;

use App\Enums\EffectReturnDestination;
use App\Enums\EffectRoutingMode;
use App\Enums\EffectRoutingTargetSection;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConsoleEffectPackageItemRequest extends FormRequest
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
            'preferred_slot_number' => ['nullable', 'integer', 'min:1', 'max:8'],
            'routing_mode' => ['nullable', 'string', 'in:'.implode(',', EffectRoutingMode::values())],
            'target_sections' => ['nullable', 'array'],
            'target_sections.*' => ['string', 'distinct', 'in:'.implode(',', EffectRoutingTargetSection::selectableValues())],
            'return_destination' => ['nullable', 'string', 'in:'.implode(',', EffectReturnDestination::values())],
            'default_return_level' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'parameters' => ['nullable', 'array'],
            'parameters.*' => ['nullable', 'string', 'max:6'],
        ];
    }
}
