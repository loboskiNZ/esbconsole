<?php

namespace App\Services;

use InvalidArgumentException;

class RuntimeIdentityValidator
{
    private const PATTERN = '/^\d{3}\.\d{3}$/';

    /**
     * @return array{song_code: string, cue_number: string}
     */
    public static function parse(string $runtimeIdentity): array
    {
        if (! preg_match(self::PATTERN, $runtimeIdentity)) {
            throw new InvalidArgumentException(
                'Runtime identity must match NNN.NNN format (e.g. 001.003).',
            );
        }

        [$songCode, $cueNumber] = explode('.', $runtimeIdentity, 2);

        return [
            'song_code' => $songCode,
            'cue_number' => $cueNumber,
        ];
    }
}
