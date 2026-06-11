<?php

namespace App\Services;

class AdapterKeyResolver
{
    public const ADAPTER_X32 = 'x32';

    public const ADAPTER_LIGHTING = 'lighting';

    public const ADAPTER_MUSICIAN_DEVICE = 'musician_device';

    public const ADAPTER_VIDEO = 'video';

    public const ADAPTER_CUSTOM = 'custom';

    public function resolve(string $actionTypeCode): string
    {
        if (str_starts_with($actionTypeCode, 'X32_')) {
            return self::ADAPTER_X32;
        }

        if (str_starts_with($actionTypeCode, 'LIGHT_')) {
            return self::ADAPTER_LIGHTING;
        }

        if (str_starts_with($actionTypeCode, 'MUSICIAN_')) {
            return self::ADAPTER_MUSICIAN_DEVICE;
        }

        if (str_starts_with($actionTypeCode, 'VIDEO_')) {
            return self::ADAPTER_VIDEO;
        }

        if ($actionTypeCode === 'CUSTOM') {
            return self::ADAPTER_CUSTOM;
        }

        return self::ADAPTER_CUSTOM;
    }
}
