<?php

namespace Database\Factories;

use App\Models\IntegrationDevice;
use App\Models\Performance;
use App\Models\PerformanceDeviceAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceDeviceAssignment>
 */
class PerformanceDeviceAssignmentFactory extends Factory
{
    protected $model = PerformanceDeviceAssignment::class;

    public function definition(): array
    {
        return [
            'performance_id' => Performance::factory(),
            'integration_device_id' => IntegrationDevice::factory(),
            'role' => PerformanceDeviceAssignment::ROLE_FOH,
        ];
    }

    public function forPerformance(Performance $performance): static
    {
        return $this->state(fn () => [
            'performance_id' => $performance->id,
        ]);
    }

    public function forDevice(IntegrationDevice $device): static
    {
        return $this->state(fn () => [
            'integration_device_id' => $device->id,
        ]);
    }
}
