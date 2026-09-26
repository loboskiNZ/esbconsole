<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestListEntryRequest extends FormRequest
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
            'guest_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'category' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{guest_name: string, email: ?string, quantity: int, category: ?string, notes: ?string}
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        return [
            'guest_name' => trim((string) $validated['guest_name']),
            'email' => isset($validated['email']) ? strtolower(trim((string) $validated['email'])) : null,
            'quantity' => (int) $validated['quantity'],
            'category' => isset($validated['category']) && trim((string) $validated['category']) !== ''
                ? trim((string) $validated['category'])
                : null,
            'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                ? trim((string) $validated['notes'])
                : null,
        ];
    }
}
