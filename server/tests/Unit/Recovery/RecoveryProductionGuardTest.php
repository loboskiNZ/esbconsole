<?php

namespace Tests\Unit\Recovery;

use App\Services\Recovery\RecoveryProductionGuard;
use RuntimeException;
use Tests\TestCase;

class RecoveryProductionGuardTest extends TestCase
{
  public function test_blocks_production_app_env(): void
  {
    config(['app.env' => 'production']);
    app()->detectEnvironment(fn () => 'production');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('production');

    app(RecoveryProductionGuard::class)->assertRecoveryAllowed();
  }

  public function test_blocks_forbidden_production_host(): void
  {
    config([
      'app.env' => 'local',
      'recovery.source_connection' => 'recovery_source',
      'database.connections.recovery_source' => [
        'driver' => 'pgsql',
        'host' => 'pr-esbdata-68105.db.on-forge.com',
        'database' => 'esb_dev',
      ],
      'recovery.target_connection' => 'recovery_target',
      'database.connections.recovery_target' => [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'database' => 'esb_ccmm_validation',
      ],
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('forbidden host');

    app(RecoveryProductionGuard::class)->assertRecoveryAllowed();
  }

  public function test_blocks_defaultdb_database_name(): void
  {
    config([
      'app.env' => 'local',
      'recovery.source_connection' => 'recovery_source',
      'database.connections.recovery_source' => [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'database' => 'defaultdb',
      ],
      'recovery.target_connection' => 'recovery_target',
      'database.connections.recovery_target' => [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'database' => 'esb_ccmm_validation',
      ],
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('forbidden database');

    app(RecoveryProductionGuard::class)->assertRecoveryAllowed();
  }

  public function test_allows_local_sqlite_connections(): void
  {
    config([
      'app.env' => 'local',
      'recovery.source_connection' => 'recovery_source',
      'database.connections.recovery_source' => config('database.connections.sqlite'),
      'recovery.target_connection' => 'recovery_target',
      'database.connections.recovery_target' => config('database.connections.sqlite'),
    ]);

    app(RecoveryProductionGuard::class)->assertRecoveryAllowed();

    $this->assertTrue(true);
  }

  public function test_write_execution_requires_local_acknowledgement(): void
  {
    config([
      'app.env' => 'local',
      'recovery.require_local_acknowledgement' => false,
      'recovery.source_connection' => 'recovery_source',
      'database.connections.recovery_source' => config('database.connections.sqlite'),
      'recovery.target_connection' => 'recovery_target',
      'database.connections.recovery_target' => config('database.connections.sqlite'),
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('RECOVERY_LOCAL_ACKNOWLEDGED');

    app(RecoveryProductionGuard::class)->assertRecoveryAllowed(executeWrites: true);
  }
}
