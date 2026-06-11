<?php

namespace App\Services\Integration;

use App\Models\IntegrationDevice;
use Illuminate\Support\Collection;

class IntegrationDeviceRegistry
{
    /**
     * @return Collection<int, IntegrationDevice>
     */
    public function findEnabledByBandAndType(int $bandId, string $deviceType): Collection
    {
        return IntegrationDevice::query()
            ->where('band_id', $bandId)
            ->where('device_type', $deviceType)
            ->where('enabled', true)
            ->orderBy('device_key')
            ->get();
    }

    public function findEnabledByBandAndDeviceKey(int $bandId, string $deviceKey): ?IntegrationDevice
    {
        return IntegrationDevice::query()
            ->where('band_id', $bandId)
            ->where('device_key', $deviceKey)
            ->where('enabled', true)
            ->first();
    }

    public function resolve(int $bandId, string $deviceType, ?string $deviceKey = null): ?IntegrationDevice
    {
        if ($deviceKey !== null) {
            $device = $this->findEnabledByBandAndDeviceKey($bandId, $deviceKey);

            if ($device === null || $device->device_type !== $deviceType) {
                return null;
            }

            return $device;
        }

        return $this->findEnabledByBandAndType($bandId, $deviceType)->first();
    }
}
