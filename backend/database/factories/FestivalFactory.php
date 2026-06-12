<?php

namespace Database\Factories;

use App\Enums\FestivalApplicationStatus;
use App\Models\Band;
use App\Models\Festival;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Festival>
 */
class FestivalFactory extends Factory
{
    protected $model = Festival::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'name' => fake()->unique()->words(3, true).' Festival',
            'country' => fake()->country(),
            'city' => fake()->city(),
            'website' => fake()->url(),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->safeEmail(),
            'application_url' => fake()->url(),
            'application_deadline' => fake()->date(),
            'festival_date_notes' => fake()->sentence(),
            'application_status' => FestivalApplicationStatus::NotApplied,
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
