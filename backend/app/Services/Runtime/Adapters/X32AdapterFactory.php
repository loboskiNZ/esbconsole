<?php

namespace App\Services\Runtime\Adapters;

use App\Contracts\X32\UdpSocketClientInterface;
use App\Services\Integration\IntegrationDeviceRegistry;
use App\Services\X32\DryRunX32Transport;
use App\Services\X32\FakeUdpSocketClient;
use App\Services\X32\OscX32Transport;
use App\Services\X32\X32DispatchContextResolver;
use App\Services\X32\X32OscSceneRecallPacketBuilder;
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

    public static function createOscTransport(
        bool $liveSendingEnabled = false,
        ?UdpSocketClientInterface $socketClient = null,
    ): X32Adapter {
        $deviceRegistry = new IntegrationDeviceRegistry;

        return new X32Adapter(
            contextResolver: new X32DispatchContextResolver($deviceRegistry),
            sceneParameterResolver: new X32SceneParameterResolver,
            transport: new OscX32Transport(
                packetBuilder: new X32OscSceneRecallPacketBuilder,
                socketClient: $socketClient ?? new FakeUdpSocketClient,
                liveSendingEnabled: $liveSendingEnabled,
            ),
            dryRun: ! $liveSendingEnabled,
        );
    }

    public static function createLiveOsc(UdpSocketClientInterface $socketClient): X32Adapter
    {
        return self::createOscTransport(
            liveSendingEnabled: true,
            socketClient: $socketClient,
        );
    }
}
