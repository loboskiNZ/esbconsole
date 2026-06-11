<?php

namespace App\Services\X32;

use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\RuntimeDispatchItem;

readonly class X32DispatchContext
{
    public function __construct(
        public int $bandId,
        public int $performanceId,
        public IntegrationDevice $device,
        public IntegrationConnectionProfile $profile,
        public string $selectionSource,
    ) {}
}

class X32DispatchContextResolver
{
    public function __construct(
        private readonly X32DeviceSelector $deviceSelector,
    ) {}

    public function resolve(int $runtimeDispatchItemId): ?X32DispatchContext
    {
        $dispatchItem = RuntimeDispatchItem::query()
            ->with([
                'runtimeDispatch.performance',
                'runtimeDispatch.runtimeActionPlan',
            ])
            ->find($runtimeDispatchItemId);

        $performance = $dispatchItem?->runtimeDispatch?->performance;

        if ($performance === null) {
            return null;
        }

        $selection = $this->deviceSelector->select(
            bandId: $performance->band_id,
            performanceId: $performance->id,
            deviceKey: $this->resolveDeviceKeyFromPayload($dispatchItem->payload ?? []),
        );

        if ($selection === null) {
            return null;
        }

        $device = $selection->device;
        $device->loadMissing('integrationConnectionProfiles');

        $profile = $device->integrationConnectionProfiles
            ->where('enabled', true)
            ->first();

        if ($profile === null) {
            return null;
        }

        return new X32DispatchContext(
            bandId: $performance->band_id,
            performanceId: $performance->id,
            device: $device,
            profile: $profile,
            selectionSource: $selection->selectionSource,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDeviceKeyFromPayload(array $payload): ?string
    {
        if (isset($payload['device_key']) && is_string($payload['device_key']) && $payload['device_key'] !== '') {
            return $payload['device_key'];
        }

        $parameters = $payload['parameters'] ?? null;

        if (is_array($parameters)
            && isset($parameters['device_key'])
            && is_string($parameters['device_key'])
            && $parameters['device_key'] !== '') {
            return $parameters['device_key'];
        }

        return null;
    }
}
