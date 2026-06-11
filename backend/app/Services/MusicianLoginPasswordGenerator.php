<?php

namespace App\Services;

class MusicianLoginPasswordGenerator
{
    private const UPPERCASE = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWERCASE = 'abcdefghjkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    private const SYMBOLS = '!@#$%&*?';

    public function generate(): string
    {
        $characters = [
            $this->pick(self::UPPERCASE),
            $this->pick(self::LOWERCASE),
            $this->pick(self::DIGITS),
            $this->pick(self::SYMBOLS),
        ];

        $pool = self::UPPERCASE.self::LOWERCASE.self::DIGITS.self::SYMBOLS;

        while (count($characters) < 8) {
            $characters[] = $this->pick($pool);
        }

        shuffle($characters);

        $password = implode('', $characters);

        if (! $this->satisfiesRequirements($password)) {
            return $this->generate();
        }

        return $password;
    }

    public function satisfiesRequirements(string $password): bool
    {
        return strlen($password) === 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }

    private function pick(string $pool): string
    {
        return $pool[random_int(0, strlen($pool) - 1)];
    }
}
