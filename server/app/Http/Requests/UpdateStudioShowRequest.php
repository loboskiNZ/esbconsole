<?php

namespace App\Http\Requests;

use App\Models\Show;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudioShowRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:5000'],
            'lifecycle_state' => ['nullable', 'string', Rule::in([Show::STATE_DRAFT, Show::STATE_PLANNED])],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     description: string|null,
     *     lifecycle_state: string,
     * }
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        return [
            'name' => trim((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'lifecycle_state' => $validated['lifecycle_state'] ?? Show::STATE_DRAFT,
        ];
    }
}
