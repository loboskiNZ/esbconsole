<?php

namespace App\Exceptions\Runtime;

use RuntimeException;

class UnsupportedDispatchItemException extends RuntimeException
{
    public static function forAdapter(string $adapterKey, int $dispatchItemId): self
    {
        return new self(
            "Adapter [{$adapterKey}] does not support runtime dispatch item [{$dispatchItemId}].",
        );
    }
}
