<?php

namespace App\Services\X32;

use App\Contracts\X32\X32OscConsoleClientInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Live UDP OSC client for X32 console parameter read/write.
 *
 * Requires explicit live mode — not used in automated tests by default.
 */
class OscUdpX32OscConsoleClient implements X32OscConsoleClientInterface
{
    public function __construct(
        private readonly X32OscMessageCodec $codec,
        private readonly float $timeoutSeconds = 0.5,
    ) {}

    public function queryFloat(string $host, int $port, string $path): float
    {
        $response = $this->transceive($host, $port, $this->codec->buildQuery($path), $path);

        return $this->codec->parseFloatResponse($response);
    }

    public function queryInt(string $host, int $port, string $path): int
    {
        $response = $this->transceive($host, $port, $this->codec->buildQuery($path), $path);

        return $this->codec->parseIntResponse($response);
    }

    public function queryString(string $host, int $port, string $path): string
    {
        $response = $this->transceive($host, $port, $this->codec->buildQuery($path), $path);

        return $this->codec->parseStringResponse($response);
    }

    public function queryOn(string $host, int $port, string $path): int
    {
        $response = $this->transceive($host, $port, $this->codec->buildQuery($path), $path);

        return $this->codec->parseOnResponse($response);
    }

    public function setFloat(string $host, int $port, string $path, float $value): void
    {
        $this->sendOnly($host, $port, $this->codec->buildFloat($path, $value));
    }

    public function setInt(string $host, int $port, string $path, int $value): void
    {
        $this->sendOnly($host, $port, $this->codec->buildInt($path, $value));
    }

    public function sendPacket(string $host, int $port, string $payload): void
    {
        $this->sendOnly($host, $port, $payload);
    }

    private function transceive(string $host, int $port, string $payload, ?string $oscPath = null): string
    {
        $this->logOscDebug('OSC request', [
            'host' => $host,
            'port' => $port,
            'path' => $oscPath,
            'payload_bytes' => strlen($payload),
        ]);

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket === false) {
            throw new RuntimeException('Failed to create UDP socket.');
        }

        $seconds = (int) floor($this->timeoutSeconds);
        $microseconds = (int) round(($this->timeoutSeconds - $seconds) * 1_000_000);
        $timeout = ['sec' => $seconds, 'usec' => $microseconds];

        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, $timeout);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $timeout);

        try {
            $sent = socket_sendto($socket, $payload, strlen($payload), 0, $host, $port);

            if ($sent === false) {
                throw new RuntimeException('OSC UDP send failed.');
            }

            $response = '';
            $from = '';
            $fromPort = 0;
            $received = @socket_recvfrom($socket, $response, 4096, 0, $from, $fromPort);

            if ($received === false || $received === 0) {
                $this->logOscDebug('OSC timeout', [
                    'host' => $host,
                    'port' => $port,
                    'path' => $oscPath,
                    'timeout_seconds' => $this->timeoutSeconds,
                ]);

                $pathLabel = $oscPath ?? 'unknown path';

                throw new RuntimeException(sprintf(
                    'OSC UDP receive timed out or failed for %s (%s:%d).',
                    $pathLabel,
                    $host,
                    $port,
                ));
            }

            $this->logOscDebug('OSC response', [
                'host' => $host,
                'port' => $port,
                'path' => $oscPath,
                'response_bytes' => $received,
            ]);

            return $response;
        } catch (Throwable $exception) {
            $this->logOscDebug('OSC exception', [
                'host' => $host,
                'port' => $port,
                'path' => $oscPath,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        } finally {
            socket_close($socket);
        }
    }

    private function sendOnly(string $host, int $port, string $payload): void
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket === false) {
            throw new RuntimeException('Failed to create UDP socket.');
        }

        $seconds = (int) floor($this->timeoutSeconds);
        $microseconds = (int) round(($this->timeoutSeconds - $seconds) * 1_000_000);
        $timeout = ['sec' => $seconds, 'usec' => $microseconds];

        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, $timeout);

        $sent = socket_sendto($socket, $payload, strlen($payload), 0, $host, $port);

        socket_close($socket);

        if ($sent === false) {
            throw new RuntimeException('OSC UDP send failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logOscDebug(string $message, array $context = []): void
    {
        if (! config('services.console_learn.osc_debug', false)) {
            return;
        }

        Log::info('[console-learn] '.$message, $context);
    }
}
