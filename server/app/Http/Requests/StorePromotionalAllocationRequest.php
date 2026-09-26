<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionalAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDirector() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:120'],
            'holder_name' => ['nullable', 'string', 'max:160'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array{label: ?string, holder_name: ?string, quantity: int}
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        return [
            'label' => isset($validated['label']) && trim((string) $validated['label']) !== ''
                ? trim((string) $validated['label'])
                : null,
            'holder_name' => isset($validated['holder_name']) && trim((string) $validated['holder_name']) !== ''
                ? trim((string) $validated['holder_name'])
                : null,
            'quantity' => (int) $validated['quantity'],
        ];
    }
}
