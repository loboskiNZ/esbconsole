<?php

namespace App\Services\X32;

use App\Contracts\X32\X32TransportInterface;

class DryRunX32Transport implements X32TransportInterface
{
    public function recallScene(X32SceneRecallCommand $command): X32TransportResult
    {
        return new X32TransportResult(
            success: true,
            mode: 'dry_run',
            scene: $command->scene,
            message: 'X32 scene recall prepared in dry-run mode.',
            context: [
                'adapter' => 'x32',
                'mode' => 'dry_run',
                'scene' => $command->scene,
                'device_key' => $command->deviceKey,
                'profile_name' => $command->profileName,
                'protocol' => $command->protocol,
                'host' => $command->host,
                'port' => $command->port,
            ],
        );
    }
}
