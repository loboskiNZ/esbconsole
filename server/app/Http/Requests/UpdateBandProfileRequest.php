<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBandProfileRequest extends FormRequest
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
        $logoMaxKb = (int) config('portal.band_logo_max_kb', 5120);
        $photoMaxKb = (int) config('portal.band_photo_max_kb', 25600);

        return [
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'styles' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:'.$logoMaxKb],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$photoMaxKb],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        return $this->safe()->only(['name', 'bio', 'styles']);
    }
}
