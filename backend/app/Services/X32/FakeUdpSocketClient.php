<?php

namespace App\Services\X32;

use App\Contracts\X32\UdpSocketClientInterface;
use RuntimeException;

class FakeUdpSocketClient implements UdpSocketClientInterface
{
    /** @var list<array{host: string, port: int, payload: string}> */
    public array $sent = [];

    public bool $shouldFail = false;

    public function send(string $host, int $port, string $payload): int
    {
        if ($this->shouldFail) {
            throw new RuntimeException('UDP socket send failed.');
        }

        $this->sent[] = [
            'host' => $host,
            'port' => $port,
            'payload' => $payload,
        ];

        return strlen($payload);
    }
}
