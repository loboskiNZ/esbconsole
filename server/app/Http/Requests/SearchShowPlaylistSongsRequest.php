<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchShowPlaylistSongsRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:1', 'max:120'],
        ];
    }
}
