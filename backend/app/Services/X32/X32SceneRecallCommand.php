<?php

namespace App\Services\X32;

readonly class X32SceneRecallCommand
{
    public function __construct(
        public string $scene,
        public string $deviceKey,
        public string $profileName,
        public string $protocol,
        public ?string $host,
        public ?int $port,
        public bool $dryRun,
    ) {}
}
