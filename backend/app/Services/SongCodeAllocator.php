<?php

namespace App\Services;

use App\Models\Band;
use RuntimeException;

class SongCodeAllocator
{
    public function nextForBand(Band $band): string
    {
        $used = $band->songs()
            ->pluck('song_code')
            ->map(fn (string $code) => (int) $code)
            ->all();

        for ($i = 1; $i <= 999; $i++) {
            if (! in_array($i, $used, true)) {
                return str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            }
        }

        throw new RuntimeException('No song codes available for this band.');
    }
}
