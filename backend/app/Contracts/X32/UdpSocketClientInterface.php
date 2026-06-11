<?php

namespace App\Contracts\X32;

interface UdpSocketClientInterface
{
    public function send(string $host, int $port, string $payload): int;
}
