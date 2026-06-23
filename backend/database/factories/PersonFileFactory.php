<?php

namespace Database\Factories;

use App\Enums\PersonFileType;
use App\Models\Person;
use App\Models\PersonFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonFile>
 */
class PersonFileFactory extends Factory
{
    protected $model = PersonFile::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'file_type' => PersonFileType::PassportPhoto,
            'file_path' => 'people/'.fake()->uuid().'/passport.jpg',
            'original_filename' => 'passport.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(10_000, 5_000_000),
            'expires_at' => null,
            'notes' => null,
            'is_public' => false,
        ];
    }
}
