<?php

namespace App\Services\X32;

use App\Contracts\X32\UdpSocketClientInterface;
use App\Contracts\X32\X32TransportInterface;
use App\Models\IntegrationConnectionProfile;
use Throwable;

class OscX32Transport implements X32TransportInterface
{
    public const DEFAULT_OSC_PORT = 10023;

    public function __construct(
        private readonly X32OscSceneRecallPacketBuilder $packetBuilder,
        private readonly UdpSocketClientInterface $socketClient,
        private readonly bool $liveSendingEnabled = false,
    ) {}

    public function recallScene(X32SceneRecallCommand $command): X32TransportResult
    {
        if ($command->protocol !== IntegrationConnectionProfile::PROTOCOL_OSC) {
            return $this->failedResult(
                scene: $command->scene,
                message: 'OscX32Transport only supports OSC connection profiles.',
                mode: $command->runtimeMode,
                context: [
                    'protocol' => $command->protocol,
                ],
            );
        }

        $host = $command->host;
        $port = $command->port ?? self::DEFAULT_OSC_PORT;
        $oscPath = $this->packetBuilder->oscPath($command->scene);
        $packet = $this->packetBuilder->build($command->scene);

        if ($host === null || $host === '') {
            return $this->failedResult(
                scene: $command->scene,
                message: 'OSC transport requires a host.',
                mode: $command->runtimeMode,
                context: [
                    'osc_path' => $oscPath,
                    'port' => $port,
                ],
            );
        }

        if ($port <= 0) {
            return $this->failedResult(
                scene: $command->scene,
                message: 'OSC transport requires a valid port.',
                mode: $command->runtimeMode,
                context: [
                    'osc_path' => $oscPath,
                    'host' => $host,
                ],
            );
        }

        if ($command->runtimeMode === X32RuntimeModeResolver::MODE_DISABLED) {
            return new X32TransportResult(
                success: false,
                mode: X32RuntimeModeResolver::MODE_DISABLED,
                scene: $command->scene,
                message: 'X32 runtime mode is disabled.',
                context: $this->baseContext($command, $host, $port, $oscPath, $packet, bytesSent: 0),
            );
        }

        if ($command->runtimeMode === X32RuntimeModeResolver::MODE_DRY_RUN
            || $command->dryRun
            || ! $this->liveSendingEnabled) {
            return new X32TransportResult(
                success: true,
                mode: X32RuntimeModeResolver::MODE_DRY_RUN,
                scene: $command->scene,
                message: 'X32 OSC scene recall prepared without live network send.',
                context: $this->baseContext($command, $host, $port, $oscPath, $packet, bytesSent: 0),
            );
        }

        if ($command->runtimeMode !== X32RuntimeModeResolver::MODE_LIVE) {
            return $this->failedResult(
                scene: $command->scene,
                message: 'Invalid X32 runtime mode for live OSC transport.',
                mode: X32RuntimeModeResolver::MODE_DISABLED,
                context: $this->baseContext($command, $host, $port, $oscPath, $packet, bytesSent: 0),
            );
        }

        try {
            $bytesSent = $this->socketClient->send($host, $port, $packet);

            return new X32TransportResult(
                success: true,
                mode: X32RuntimeModeResolver::MODE_LIVE,
                scene: $command->scene,
                message: 'X32 OSC scene recall sent.',
                context: $this->baseContext($command, $host, $port, $oscPath, $packet, $bytesSent),
            );
        } catch (Throwable $exception) {
            return $this->failedResult(
                scene: $command->scene,
                message: $exception->getMessage(),
                mode: X32RuntimeModeResolver::MODE_LIVE,
                context: $this->baseContext($command, $host, $port, $oscPath, $packet, bytesSent: 0),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function failedResult(string $scene, string $message, string $mode, array $context = []): X32TransportResult
    {
        return new X32TransportResult(
            success: false,
            mode: $mode,
            scene: $scene,
            message: $message,
            context: array_merge([
                'adapter' => 'x32',
                'mode' => $mode,
            ], $context),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function baseContext(
        X32SceneRecallCommand $command,
        string $host,
        int $port,
        string $oscPath,
        string $packet,
        int $bytesSent,
    ): array {
        return [
            'adapter' => 'x32',
            'mode' => $command->runtimeMode,
            'host' => $host,
            'port' => $port,
            'scene' => $command->scene,
            'osc_path' => $oscPath,
            'device_key' => $command->deviceKey,
            'profile_name' => $command->profileName,
            'bytes_sent' => $bytesSent,
            'packet_size' => strlen($packet),
        ];
    }
}
