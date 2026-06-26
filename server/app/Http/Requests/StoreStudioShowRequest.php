<?php

namespace App\Http\Requests;

use App\Models\Show;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudioShowRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'venue_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lifecycle_state' => ['nullable', 'string', Rule::in([Show::STATE_DRAFT, Show::STATE_PLANNED])],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     scheduled_at: string|null,
     *     venue_location: string|null,
     *     notes: string|null,
     *     lifecycle_state: string,
     * }
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        return [
            'name' => trim((string) $validated['name']),
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'venue_location' => isset($validated['venue_location']) ? trim((string) $validated['venue_location']) : null,
            'notes' => $validated['notes'] ?? null,
            'lifecycle_state' => $validated['lifecycle_state'] ?? Show::STATE_DRAFT,
        ];
    }
}
