<?php

namespace App\Contracts\X32;

interface X32OscConsoleClientInterface
{
    public function queryFloat(string $host, int $port, string $path): float;

    public function queryInt(string $host, int $port, string $path): int;

    public function queryString(string $host, int $port, string $path): string;

    public function setFloat(string $host, int $port, string $path, float $value): void;

    public function setInt(string $host, int $port, string $path, int $value): void;

    public function sendPacket(string $host, int $port, string $payload): void;
}
