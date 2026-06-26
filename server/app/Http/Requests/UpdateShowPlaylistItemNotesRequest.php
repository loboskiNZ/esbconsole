<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShowPlaylistItemNotesRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
