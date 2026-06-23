<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class PersonSecureFieldEncryption
{
    public const KEY_CONTEXT = 'person_secure_field';

    public function encrypt(string $plainText): string
    {
        return Crypt::encryptString($plainText);
    }

    public function decrypt(string $encryptedValue): string
    {
        return Crypt::decryptString($encryptedValue);
    }

    public function lastFourPreview(string $plainText): string
    {
        $length = mb_strlen($plainText);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return mb_substr($plainText, -4);
    }
}
