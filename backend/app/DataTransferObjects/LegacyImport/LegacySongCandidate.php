<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacySongCandidate
{
    public function __construct(
        public string $legacySongId,
        public string $songCode,
        public string $title,
        public ?string $artist,
        public ?int $bpm,
        public int $playlistPosition,
        public ?int $abletonPgm,
    ) {}
}
