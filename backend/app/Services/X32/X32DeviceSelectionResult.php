<?php

namespace App\Services\X32;

use App\Models\IntegrationDevice;

readonly class X32DeviceSelectionResult
{
    public const SOURCE_PERFORMANCE_ASSIGNMENT = 'performance_assignment';

    public const SOURCE_EXPLICIT_DEVICE_KEY = 'explicit_device_key';

    public const SOURCE_BAND_FALLBACK = 'band_fallback';

    public function __construct(
        public IntegrationDevice $device,
        public string $selectionSource,
    ) {}

    public function deviceId(): int
    {
        return $this->device->id;
    }

    public function deviceKey(): string
    {
        return $this->device->device_key;
    }

    /**
     * @return array<string, mixed>
     */
    public function toContext(): array
    {
        return [
            'device_id' => $this->deviceId(),
            'device_key' => $this->deviceKey(),
            'selection_source' => $this->selectionSource,
        ];
    }
}
