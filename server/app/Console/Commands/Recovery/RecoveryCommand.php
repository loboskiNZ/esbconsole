<?php

namespace App\Console\Commands\Recovery;

use App\Services\Recovery\RecoveryProductionGuard;
use Illuminate\Console\Command;
use RuntimeException;

abstract class RecoveryCommand extends Command
{
  protected function guard(bool $executeWrites = false): RecoveryProductionGuard
  {
    $guard = app(RecoveryProductionGuard::class);
    $guard->assertRecoveryAllowed($executeWrites);

    return $guard;
  }

  protected function resolveDryRun(bool $executeFlag): bool
  {
    if ($executeFlag) {
      return false;
    }

    return true;
  }

  protected function sourceConnection(): string
  {
    return (string) config('recovery.source_connection', 'recovery_source');
  }

  protected function targetConnection(): string
  {
    return (string) config('recovery.target_connection', 'recovery_target');
  }

  protected function failBlocked(RuntimeException $exception): int
  {
    $this->error($exception->getMessage());

    return self::FAILURE;
  }
}
