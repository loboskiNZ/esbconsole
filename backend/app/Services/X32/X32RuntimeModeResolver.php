<?php

namespace App\Services\X32;

class X32RuntimeModeResolver
{
    public const MODE_DISABLED = 'disabled';

    public const MODE_DRY_RUN = 'dry_run';

    public const MODE_LIVE = 'live';

    /**
     * @param  array<string, mixed>|null  $configuration
     */
    public function resolve(?array $configuration): string
    {
        $mode = $configuration['runtime_mode'] ?? self::MODE_DRY_RUN;

        if (! is_string($mode)) {
            return self::MODE_DISABLED;
        }

        return match ($mode) {
            self::MODE_DISABLED, self::MODE_DRY_RUN, self::MODE_LIVE => $mode,
            default => self::MODE_DISABLED,
        };
    }

    public function isLive(string $runtimeMode): bool
    {
        return $runtimeMode === self::MODE_LIVE;
    }
}
