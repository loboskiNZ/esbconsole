<?php

namespace App\Contracts\X32;

use App\Services\X32\X32SceneRecallCommand;
use App\Services\X32\X32TransportResult;

interface X32TransportInterface
{
    public function recallScene(X32SceneRecallCommand $command): X32TransportResult;
}
