<?php

/**
 * Governed CCMM migration paths (repo root).
 * Loaded by server/ and backend/ AppServiceProvider — single authority, no duplication.
 */
function ccmm_migration_paths(): array
{
    $databaseRoot = __DIR__;

    return [
        $databaseRoot.'/migrations/ccmm',
        $databaseRoot.'/migrations/recovery',
    ];
}
