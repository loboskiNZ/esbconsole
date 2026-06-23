<?php

namespace App\Support;

class ProfileBio
{
    public const MAX_WORDS = 100;

    public static function wordCount(?string $bio): int
    {
        if ($bio === null || trim($bio) === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', trim($bio), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    public static function exceedsLimit(?string $bio): bool
    {
        return self::wordCount($bio) > self::MAX_WORDS;
    }
}
