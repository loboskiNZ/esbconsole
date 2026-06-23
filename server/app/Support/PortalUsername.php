<?php

namespace App\Support;

class PortalUsername
{
    public static function normalize(string $username): string
    {
        return strtolower(trim($username));
    }

    public static function isValid(string $username): bool
    {
        $normalized = self::normalize($username);

        if ($normalized === '' || strlen($normalized) < 3 || strlen($normalized) > 32) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]+$/', $normalized);
    }
}
