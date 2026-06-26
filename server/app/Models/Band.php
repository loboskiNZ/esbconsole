<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Band extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'name',
        'short_name',
        'tagline',
        'hometown',
        'formation_year',
        'bio',
        'short_bio',
        'full_bio',
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
        'logo_path',
        'photo_path',
        'press_photo_path',
        'hero_photo_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'styles' => 'array',
            'formation_year' => 'integer',
        ];
    }

    /**
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
