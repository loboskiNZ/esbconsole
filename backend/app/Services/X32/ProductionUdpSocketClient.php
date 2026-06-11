<?php

namespace App\Services\X32;

use App\Contracts\X32\UdpSocketClientInterface;
use App\Contracts\X32\UdpSocketSenderInterface;

class ProductionUdpSocketClient implements UdpSocketClientInterface
{
    private readonly UdpSocketSenderInterface $sender;

    public function __construct(
        ?UdpSocketSenderInterface $sender = null,
        private readonly float $timeoutSeconds = 1.0,
    ) {
        $this->sender = $sender ?? new PhpUdpSocketSender;
    }

    public function send(string $host, int $port, string $payload): int
    {
        return $this->sender->send($host, $port, $payload, $this->timeoutSeconds);
    }
}
