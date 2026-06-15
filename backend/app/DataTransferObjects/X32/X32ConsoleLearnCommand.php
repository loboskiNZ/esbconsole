<?php

namespace App\DataTransferObjects\X32;

use App\Models\IntegrationDevice;

final readonly class X32ConsoleLearnCommand
{
    public function __construct(
        public IntegrationDevice $device,
        public string $requestedSceneNumber,
        public string $host,
        public int $port,
    ) {}
}
