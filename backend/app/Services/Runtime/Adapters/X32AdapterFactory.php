<?php

namespace App\Services\Runtime\Adapters;

use App\Services\Integration\IntegrationDeviceRegistry;
use App\Services\X32\DryRunX32Transport;
use App\Services\X32\X32DispatchContextResolver;
use App\Services\X32\X32SceneParameterResolver;

class X32AdapterFactory
{
    public static function createDryRun(): X32Adapter
    {
        $deviceRegistry = new IntegrationDeviceRegistry;

        return new X32Adapter(
            contextResolver: new X32DispatchContextResolver($deviceRegistry),
            sceneParameterResolver: new X32SceneParameterResolver,
            transport: new DryRunX32Transport,
            dryRun: true,
        );
    }
}
