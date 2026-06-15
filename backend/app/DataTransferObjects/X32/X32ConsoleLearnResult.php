<?php

namespace App\DataTransferObjects\X32;

final readonly class X32ConsoleLearnResult
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $rawSnapshot
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public bool $success,
        public array $summary,
        public array $rawSnapshot,
        public array $warnings = [],
        public array $errors = [],
    ) {}
}
