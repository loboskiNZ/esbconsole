<?php

namespace App\Services\X32;

readonly class X32TransportResult
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public bool $success,
        public string $mode,
        public string $scene,
        public ?string $message,
        public array $context,
    ) {}
}
