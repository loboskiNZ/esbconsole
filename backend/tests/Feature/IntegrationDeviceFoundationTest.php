<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Services\Integration\IntegrationDeviceRegistry;
use App\Services\Integration\IntegrationDeviceValidator;
use App\Services\Integration\IntegrationValidationResult;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationDeviceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_device_can_be_created_for_a_band(): void
    {
        $band = Band::factory()->create();

        $device = IntegrationDevice::factory()->forBand($band)->create([
            'device_key' => 'main-x32',
            'name' => 'Main X32',
            'device_type' => IntegrationDevice::TYPE_X32,
        ]);

        $this->assertTrue($device->band->is($band));
        $this->assertTrue($band->fresh()->integrationDevices->contains($device));
        $this->assertDatabaseHas('integration_devices', [
            'id' => $device->id,
            'band_id' => $band->id,
            'device_key' => 'main-x32',
        ]);
    }

    public function test_device_key_is_unique_per_band(): void
    {
        $band = Band::factory()->create();

        IntegrationDevice::factory()->forBand($band)->create([
            'device_key' => 'main-x32',
        ]);

        $this->expectException(QueryException::class);
        IntegrationDevice::factory()->forBand($band)->create([
            'device_key' => 'main-x32',
        ]);
    }

    public function test_same_device_key_can_exist_for_different_bands(): void
    {
        $bandA = Band::factory()->create();
        $bandB = Band::factory()->create();

        $deviceA = IntegrationDevice::factory()->forBand($bandA)->create([
            'device_key' => 'main-x32',
        ]);
        $deviceB = IntegrationDevice::factory()->forBand($bandB)->create([
            'device_key' => 'main-x32',
        ]);

        $this->assertNotSame($deviceA->id, $deviceB->id);
        $this->assertDatabaseCount('integration_devices', 2);
    }

    public function test_integration_connection_profile_belongs_to_integration_device(): void
    {
        $device = IntegrationDevice::factory()->create();
        $profile = IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'primary',
        ]);

        $this->assertTrue($profile->integrationDevice->is($device));
        $this->assertTrue($device->fresh()->integrationConnectionProfiles->contains($profile));
    }

    public function test_profile_name_is_unique_per_device(): void
    {
        $device = IntegrationDevice::factory()->create();

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'primary',
        ]);

        $this->expectException(QueryException::class);
        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'primary',
        ]);
    }

    public function test_disabled_device_validation_is_skipped_and_deterministic(): void
    {
        $device = IntegrationDevice::factory()->create([
            'enabled' => false,
            'connection_status' => IntegrationDevice::CONNECTION_STATUS_UNVALIDATED,
        ]);

        $result = app(IntegrationDeviceValidator::class)->validate($device);

        $this->assertFalse($result->success);
        $this->assertSame(IntegrationValidationResult::STATUS_SKIPPED, $result->status);
        $this->assertSame(
            IntegrationDevice::CONNECTION_STATUS_DISABLED,
            $device->fresh()->connection_status,
        );
    }

    public function test_missing_required_host_or_port_for_network_profile_is_invalid(): void
    {
        $device = IntegrationDevice::factory()->create([
            'device_type' => IntegrationDevice::TYPE_LIGHTING,
            'enabled' => true,
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'primary',
            'protocol' => IntegrationConnectionProfile::PROTOCOL_UDP,
            'host' => null,
            'port' => null,
        ]);

        $result = app(IntegrationDeviceValidator::class)->validate($device);

        $this->assertFalse($result->success);
        $this->assertSame(IntegrationValidationResult::STATUS_INVALID, $result->status);
        $this->assertStringContainsString('host and port', $result->message ?? '');
    }

    public function test_supported_profile_validation_returns_valid_without_opening_network_connection(): void
    {
        $device = IntegrationDevice::factory()->create([
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'primary',
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '192.168.1.50',
            'port' => 10023,
        ]);

        $result = app(IntegrationDeviceValidator::class)->validate($device);

        $this->assertTrue($result->success);
        $this->assertSame(IntegrationValidationResult::STATUS_VALID, $result->status);
        $this->assertSame(
            IntegrationDevice::CONNECTION_STATUS_VALID,
            $device->fresh()->connection_status,
        );
        $this->assertFalse(class_exists(\App\Services\X32NetworkClient::class));
    }

    public function test_unknown_protocol_returns_unsupported_deterministically(): void
    {
        $profile = IntegrationConnectionProfile::factory()->create([
            'protocol' => 'proprietary-protocol',
            'host' => '192.168.1.50',
            'port' => 9000,
        ]);

        $result = app(IntegrationDeviceValidator::class)->validateProfile($profile);

        $this->assertFalse($result->success);
        $this->assertSame(IntegrationValidationResult::STATUS_UNSUPPORTED, $result->status);
    }

    public function test_registry_can_find_enabled_device_by_band_and_device_type(): void
    {
        $band = Band::factory()->create();
        $device = IntegrationDevice::factory()->forBand($band)->create([
            'device_key' => 'main-x32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
        ]);

        $found = app(IntegrationDeviceRegistry::class)->resolve(
            $band->id,
            IntegrationDevice::TYPE_X32,
            'main-x32',
        );

        $this->assertNotNull($found);
        $this->assertTrue($found->is($device));
    }

    public function test_registry_does_not_return_disabled_devices_as_enabled(): void
    {
        $band = Band::factory()->create();

        IntegrationDevice::factory()->forBand($band)->create([
            'device_key' => 'main-x32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => false,
        ]);

        $registry = app(IntegrationDeviceRegistry::class);

        $this->assertNull($registry->resolve($band->id, IntegrationDevice::TYPE_X32, 'main-x32'));
        $this->assertCount(0, $registry->findEnabledByBandAndType($band->id, IntegrationDevice::TYPE_X32));
    }

    public function test_no_real_device_communication_classes_exist(): void
    {
        $this->assertFalse(class_exists(\App\Services\X32Adapter::class));
        $this->assertFalse(class_exists(\App\Services\X32NetworkClient::class));
        $this->assertFalse(class_exists(\App\Services\LightingAdapter::class));
        $this->assertFalse(class_exists(\App\Services\ExecutionDispatcher::class));

        $validatorMethods = get_class_methods(IntegrationDeviceValidator::class);
        $this->assertNotContains('connect', $validatorMethods);
        $this->assertNotContains('send', $validatorMethods);
        $this->assertNotContains('execute', $validatorMethods);

        $registryMethods = get_class_methods(IntegrationDeviceRegistry::class);
        $this->assertNotContains('dispatch', $registryMethods);
        $this->assertNotContains('execute', $registryMethods);
    }
}
