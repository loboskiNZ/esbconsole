<?php

namespace App\Contracts\X32;

interface UdpSocketSenderInterface
{
    public function send(string $host, int $port, string $payload, float $timeoutSeconds): int;
}
