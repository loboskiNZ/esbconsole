<?php

namespace App\Exceptions;

use RuntimeException;

class StudioMusicianNotLinkedException extends RuntimeException
{
    public static function forUser(): self
    {
        return new self('Your account is not linked to a musician profile yet. Ask a director to complete your roster link.');
    }
}
