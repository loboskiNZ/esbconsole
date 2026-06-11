<?php

namespace App\Exceptions\Runtime;

use RuntimeException;

class AdapterNotFoundException extends RuntimeException
{
    public static function forKey(string $adapterKey): self
    {
        return new self("No runtime adapter registered for key [{$adapterKey}].");
    }
}
