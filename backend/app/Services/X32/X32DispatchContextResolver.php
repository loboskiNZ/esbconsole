<?php

namespace App\Services\X32;

use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\RuntimeDispatchItem;
use App\Services\Integration\IntegrationDeviceRegistry;

readonly class X32DispatchContext
{
    public function __construct(
        public int $bandId,
        public int $performanceId,
        public IntegrationDevice $device,
        public IntegrationConnectionProfile $profile,
    ) {}
}

class X32DispatchContextResolver
{
    public function __construct(
        private readonly IntegrationDeviceRegistry $deviceRegistry,
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

        $device = $this->deviceRegistry->resolve(
            $performance->band_id,
            IntegrationDevice::TYPE_X32,
        );

        if ($device === null) {
            return null;
        }

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
        );
    }
}
