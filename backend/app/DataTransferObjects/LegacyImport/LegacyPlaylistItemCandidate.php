<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyPlaylistItemCandidate
{
    public function __construct(
        public string $legacySongId,
        public string $songCode,
        public int $position,
        public int $abletonPgm,
    ) {}
}
