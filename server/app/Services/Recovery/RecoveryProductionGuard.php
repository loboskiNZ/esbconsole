<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\Config;
use RuntimeException;

class RecoveryProductionGuard
{
  public function assertRecoveryAllowed(bool $executeWrites = false): void
  {
    if (app()->environment('production')) {
      throw new RuntimeException('Recovery commands are blocked when APP_ENV is production.');
    }

    if (config('app.env') === 'production') {
      throw new RuntimeException('Recovery commands are blocked when APP_ENV is production.');
    }

    foreach ([config('recovery.source_connection'), config('recovery.target_connection')] as $connection) {
      $this->assertConnectionHostAllowed((string) $connection);
    }

    if ($executeWrites && ! config('recovery.require_local_acknowledgement')) {
      throw new RuntimeException(
        'Write execution requires RECOVERY_LOCAL_ACKNOWLEDGED=true and a non-production database host.',
      );
    }
  }

  public function assertConnectionHostAllowed(string $connectionName): void
  {
    $config = Config::get("database.connections.{$connectionName}");

    if (! is_array($config)) {
      throw new RuntimeException("Database connection [{$connectionName}] is not configured.");
    }

    if (($config['driver'] ?? '') === 'sqlite') {
      return;
    }

    $host = (string) ($config['host'] ?? '');
    $database = (string) ($config['database'] ?? '');

    if ($host !== '' && $this->isBlockedHost($host)) {
      throw new RuntimeException("Recovery blocked: host [{$host}] is a production/forbidden host.");
    }

    if ($database !== '' && $this->isBlockedDatabaseName($database)) {
      throw new RuntimeException("Recovery blocked: database [{$database}] is a production/forbidden database.");
    }

    if ($host !== '' && ! $this->isAllowedHost($host) && $this->looksLikeRemoteHost($host)) {
      throw new RuntimeException("Recovery blocked: host [{$host}] is not on the local allowlist.");
    }
  }

  public function isBlockedHost(string $host): bool
  {
    $host = strtolower($host);

    foreach (config('recovery.blocked_hosts', []) as $blocked) {
      if (strtolower((string) $blocked) === $host) {
        return true;
      }
    }

    foreach (config('recovery.blocked_host_patterns', []) as $pattern) {
      if (@preg_match($pattern, $host) === 1) {
        return true;
      }
    }

    return false;
  }

  public function isBlockedDatabaseName(string $database): bool
  {
    return in_array(strtolower($database), ['defaultdb', 'band_portal'], true);
  }

  public function isAllowedHost(string $host): bool
  {
    return in_array(strtolower($host), array_map('strtolower', config('recovery.allowed_hosts', [])), true);
  }

  private function looksLikeRemoteHost(string $host): bool
  {
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return ! in_array($host, ['127.0.0.1', '::1'], true);
    }

    return ! in_array(strtolower($host), ['localhost', 'postgres'], true);
  }
}
