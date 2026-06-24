<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Production safety (PH067A)
  |--------------------------------------------------------------------------
  |
  | Recovery tooling must never run against forensic/production infrastructure.
  |
  */

  'blocked_hosts' => [
    'pr-esbdata-68105.db.on-forge.com',
    'band.edandtheshadowboys.com',
  ],

  'blocked_host_patterns' => [
    '/\.db\.on-forge\.com$/i',
    '/\.db\.ondigitalocean\.com$/i',
    '/digitalocean\.com$/i',
  ],

  'allowed_hosts' => [
    '127.0.0.1',
    'localhost',
    'postgres',
    'backend-postgres-1',
  ],

  'allowed_databases' => [
    'esb_dev',
    'esb_ccmm_validation',
    'testing',
    ':memory:',
  ],

  'source_connection' => env('RECOVERY_SOURCE_CONNECTION', 'recovery_source'),

  'target_connection' => env('RECOVERY_TARGET_CONNECTION', 'recovery_target'),

  'require_local_acknowledgement' => env('RECOVERY_LOCAL_ACKNOWLEDGED', false),

  'source_env' => env('RECOVERY_SOURCE_ENV', 'live_stage'),

];
