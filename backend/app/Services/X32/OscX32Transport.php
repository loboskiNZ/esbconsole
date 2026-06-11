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
                context: [
                    'osc_path' => $oscPath,
                    'host' => $host,
                ],
            );
        }

        if ($command->dryRun || ! $this->liveSendingEnabled) {
            return new X32TransportResult(
                success: true,
                mode: 'osc_safe',
                scene: $command->scene,
                message: 'X32 OSC scene recall prepared without live network send.',
                context: $this->baseContext($command, $host, $port, $oscPath, $packet, bytesSent: 0),
            );
        }

        try {
            $bytesSent = $this->socketClient->send($host, $port, $packet);

            return new X32TransportResult(
                success: true,
                mode: 'osc',
                scene: $command->scene,
                message: 'X32 OSC scene recall sent.',
                context: $this->baseContext($command, $host, $port, $oscPath, $packet, $bytesSent),
            );
        } catch (Throwable $exception) {
            return $this->failedResult(
                scene: $command->scene,
                message: $exception->getMessage(),
                context: $this->baseContext($command, $host, $port, $oscPath, $packet, bytesSent: 0),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function failedResult(string $scene, string $message, array $context = []): X32TransportResult
    {
        return new X32TransportResult(
            success: false,
            mode: 'osc',
            scene: $scene,
            message: $message,
            context: array_merge([
                'adapter' => 'x32',
                'mode' => 'osc',
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
            'mode' => $this->liveSendingEnabled && ! $command->dryRun ? 'osc' : 'osc_safe',
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
