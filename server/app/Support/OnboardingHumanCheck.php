<?php

namespace App\Support;

use App\Models\InviteLink;

class OnboardingHumanCheck
{
    public const SESSION_KEY = 'onboarding_human_check';

    /**
     * @return array{left: int, right: int, answer: int}
     */
    public static function issue(): array
    {
        $left = random_int(2, 9);
        $right = random_int(2, 9);

        return [
            'left' => $left,
            'right' => $right,
            'answer' => $left + $right,
        ];
    }

    /**
     * @param  array{left: int, right: int, answer: int}  $check
     */
    public static function store(array $check, string $token): void
    {
        session([self::SESSION_KEY => [
            'answer' => $check['answer'],
            'token_hash' => InviteLink::hashToken($token),
        ]]);
    }

    public static function validate(string $token, mixed $submitted): bool
    {
        $stored = session(self::SESSION_KEY);

        if (! is_array($stored)) {
            return false;
        }

        if ($stored['token_hash'] !== InviteLink::hashToken($token)) {
            return false;
        }

        if ((int) $submitted !== (int) $stored['answer']) {
            return false;
        }

        session()->forget(self::SESSION_KEY);

        return true;
    }

}
