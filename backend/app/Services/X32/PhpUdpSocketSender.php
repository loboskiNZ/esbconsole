<?php

namespace App\Services\X32;

use App\Contracts\X32\UdpSocketSenderInterface;
use RuntimeException;

class PhpUdpSocketSender implements UdpSocketSenderInterface
{
    public function send(string $host, int $port, string $payload, float $timeoutSeconds): int
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket === false) {
            throw new RuntimeException('Failed to create UDP socket.');
        }

        $seconds = (int) floor($timeoutSeconds);
        $microseconds = (int) round(($timeoutSeconds - $seconds) * 1_000_000);
        $timeout = ['sec' => $seconds, 'usec' => $microseconds];

        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, $timeout);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $timeout);

        $bytesSent = socket_sendto($socket, $payload, strlen($payload), 0, $host, $port);

        socket_close($socket);

        if ($bytesSent === false) {
            throw new RuntimeException('UDP socket send failed.');
        }

        return $bytesSent;
    }
}
