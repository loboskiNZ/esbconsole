<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShowPlaylistItemRequest extends FormRequest
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
        $bandId = (int) config('portal.band_id', 1);

        return [
            'song_id' => [
                'required',
                'integer',
                Rule::exists('songs', 'id')->where(fn ($query) => $query->where('band_id', $bandId)),
            ],
        ];
    }
}
