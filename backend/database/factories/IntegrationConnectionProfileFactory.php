<?php

namespace Database\Factories;

use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationConnectionProfile>
 */
class IntegrationConnectionProfileFactory extends Factory
{
    protected $model = IntegrationConnectionProfile::class;

    public function definition(): array
    {
        return [
            'integration_device_id' => IntegrationDevice::factory(),
            'profile_name' => 'primary',
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '192.168.1.100',
            'port' => 10023,
            'path' => null,
            'options' => null,
            'enabled' => true,
            'last_validated_at' => null,
            'last_validation_message' => null,
        ];
    }
}
