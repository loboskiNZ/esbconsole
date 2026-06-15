<?php

namespace App\Contracts\X32;

use App\DataTransferObjects\X32\X32ConsoleLearnCommand;
use App\DataTransferObjects\X32\X32ConsoleLearnResult;

/**
 * Reads a live X32/M32 console state for scene learning.
 *
 * Live hardware reads are not required for automated tests. The default binding
 * uses a fixture reader until host-level OSC query transport is implemented.
 */
interface X32ConsoleSnapshotReaderInterface
{
    public function learnScene(X32ConsoleLearnCommand $command): X32ConsoleLearnResult;
}
