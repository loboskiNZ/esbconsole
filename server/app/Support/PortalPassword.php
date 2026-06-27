<?php

namespace App\Support;

class PortalPassword
{
    public static function isValid(string $password): bool
    {
        if (strlen($password) < 8 || strlen($password) > 50) {
            return false;
        }

        if (! preg_match('/[A-Z]/', $password)) {
            return false;
        }

        if (! preg_match('/[a-z]/', $password)) {
            return false;
        }

        if (! preg_match('/[0-9]/', $password)) {
            return false;
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    public static function validationRules(): array
    {
        return [
            'required',
            'string',
            'min:8',
            'max:50',
            'regex:/[A-Z]/',
            'regex:/[a-z]/',
            'regex:/[0-9]/',
            'regex:/[^A-Za-z0-9]/',
        ];
    }
}
