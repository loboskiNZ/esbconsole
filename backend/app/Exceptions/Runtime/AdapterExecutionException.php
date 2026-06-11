<?php

namespace App\Exceptions\Runtime;

use RuntimeException;

class AdapterExecutionException extends RuntimeException
{
    public static function fromMessage(string $message): self
    {
        return new self($message);
    }
}
