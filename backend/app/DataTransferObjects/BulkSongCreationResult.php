<?php

namespace App\DataTransferObjects;

final readonly class BulkSongCreationResult
{
    /**
     * @param  array<int, array{name: string, song_code: string, song_id: int}>  $created
     * @param  array<int, array{name: string, reason: string}>  $skipped
     */
    public function __construct(
        public array $created,
        public array $skipped,
    ) {}

    public function createdCount(): int
    {
        return count($this->created);
    }

    public function skippedCount(): int
    {
        return count($this->skipped);
    }
}
