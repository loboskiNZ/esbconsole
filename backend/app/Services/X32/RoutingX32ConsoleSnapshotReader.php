<?php

namespace App\Services\X32;

use App\Contracts\X32\X32ConsoleSnapshotReaderInterface;
use App\DataTransferObjects\X32\X32ConsoleLearnCommand;
use App\DataTransferObjects\X32\X32ConsoleLearnResult;

/**
 * Routes console learning to live OSC or fixture transport based on device runtime mode.
 */
class RoutingX32ConsoleSnapshotReader implements X32ConsoleSnapshotReaderInterface
{
    public function __construct(
        private readonly FakeX32ConsoleSnapshotReader $fixtureReader,
        private readonly OscUdpX32ConsoleSnapshotReader $liveReader,
        private readonly X32RuntimeModeResolver $runtimeModeResolver,
        private readonly bool $allowLiveRoutingInTests = false,
    ) {}

    public function learnScene(X32ConsoleLearnCommand $command): X32ConsoleLearnResult
    {
        if ($this->shouldUseFixtureTransport($command)) {
            return $this->fixtureReader->learnScene($command);
        }

        $runtimeMode = $this->runtimeModeResolver->resolve($command->device->configuration ?? []);

        if ($runtimeMode !== X32RuntimeModeResolver::MODE_LIVE) {
            return new X32ConsoleLearnResult(
                success: false,
                summary: [],
                rawSnapshot: [],
                warnings: [],
                errors: [
                    sprintf(
                        'Console learning requires the device runtime_mode to be "live" (current: "%s"). Set runtime_mode to live on the X32 device configuration to recall the scene and read fader/name/colour data from the desk.',
                        $runtimeMode,
                    ),
                ],
            );
        }

        return $this->liveReader->learnScene($command);
    }

    private function shouldUseFixtureTransport(X32ConsoleLearnCommand $command): bool
    {
        if (! $this->allowLiveRoutingInTests && app()->runningUnitTests()) {
            return true;
        }

        return (bool) ($command->device->configuration['use_fixture_learning'] ?? false);
    }
}
