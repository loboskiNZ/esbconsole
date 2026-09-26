<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualAdmissionRequest extends FormRequest
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
            'attendee_name' => ['required', 'string', 'max:160'],
            'attendee_email' => ['nullable', 'email', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array{attendee_name: string, attendee_email: ?string, quantity: int}
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();
        $email = $validated['attendee_email'] ?? null;

        return [
            'attendee_name' => trim((string) $validated['attendee_name']),
            'attendee_email' => is_string($email) && trim($email) !== '' ? strtolower(trim($email)) : null,
            'quantity' => (int) $validated['quantity'],
        ];
    }
}
