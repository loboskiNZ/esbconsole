<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerformanceRsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'response' => ['required', 'string', Rule::in(['yes', 'no', 'maybe'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{response: string, notes: string|null}
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        return [
            'response' => (string) $validated['response'],
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
