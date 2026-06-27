<?php

namespace App\Http\Requests;

use App\Support\SongAssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSongAssetRequest extends FormRequest
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
        $maxKb = (int) config('portal.song_asset_max_kb', 153600);

        return [
            'file' => ['required', 'file', 'mimes:mp3,wav,mid,midi', 'max:'.$maxKb],
            'label' => ['nullable', 'string', 'max:255'],
            'asset_type' => ['required', 'string', Rule::in(SongAssetType::all())],
            'notes' => ['nullable', 'string', 'max:5000'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
