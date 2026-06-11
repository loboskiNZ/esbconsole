<?php

namespace Database\Factories;

use App\Models\Band;
use App\Models\IntegrationDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationDevice>
 */
class IntegrationDeviceFactory extends Factory
{
    protected $model = IntegrationDevice::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'device_key' => 'main-x32',
            'name' => 'Main X32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
            'connection_status' => IntegrationDevice::CONNECTION_STATUS_UNVALIDATED,
            'configuration' => null,
            'last_validated_at' => null,
        ];
    }

    public function forBand(Band $band): static
    {
        return $this->state(fn () => [
            'band_id' => $band->id,
        ]);
    }
}
