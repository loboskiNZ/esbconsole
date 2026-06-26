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
        $currentYear = (int) date('Y');

        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'hometown' => ['nullable', 'string', 'max:255'],
            'formation_year' => ['nullable', 'integer', 'min:1900', 'max:'.($currentYear + 1)],
            'short_bio' => ['nullable', 'string', 'max:1000'],
            'full_bio' => ['nullable', 'string', 'max:10000'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'styles' => ['nullable', 'string', 'max:2000'],
            'booking_email' => ['nullable', 'email', 'max:255'],
            'booking_phone' => ['nullable', 'string', 'max:64'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'spotify_url' => ['nullable', 'url', 'max:255'],
            'apple_music_url' => ['nullable', 'url', 'max:255'],
            'bandcamp_url' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:'.$logoMaxKb],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$photoMaxKb],
            'hero_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$photoMaxKb],
            'press_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$photoMaxKb],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        return $this->safe()->only([
            'name',
            'short_name',
            'tagline',
            'hometown',
            'formation_year',
            'short_bio',
            'full_bio',
            'bio',
            'styles',
            'booking_email',
            'booking_phone',
            'website_url',
            'facebook_url',
            'instagram_url',
            'tiktok_url',
            'youtube_url',
            'spotify_url',
            'apple_music_url',
            'bandcamp_url',
        ]);
    }
}
