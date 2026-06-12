<?php

namespace Database\Factories;

use App\Models\Band;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'name' => fake()->unique()->company().' Hall',
            'country' => fake()->country(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->safeEmail(),
            'facebook_tag' => '@'.fake()->userName(),
            'instagram_tag' => '@'.fake()->userName(),
            'tiktok_tag' => '@'.fake()->userName(),
            'notes' => null,
            'active' => true,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
