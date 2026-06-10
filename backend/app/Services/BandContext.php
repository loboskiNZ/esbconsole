<?php

namespace App\Services;

use App\Models\Band;

class BandContext
{
    public function resolve(): ?Band
    {
        return Band::query()->orderBy('id')->first();
    }
}
