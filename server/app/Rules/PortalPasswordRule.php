<?php

namespace App\Rules;

use App\Support\PortalPassword;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PortalPasswordRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PortalPassword::isValid($value)) {
            $fail('Password must include uppercase, lowercase, number, and symbol characters.');
        }
    }
}
