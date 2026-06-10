<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Musician;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'musician_id' => Musician::factory(),
            'device_name' => fake()->word().' Device',
            'device_type' => fake()->randomElement(['tablet', 'phone', 'laptop']),
            'active' => true,
        ];
    }
}
