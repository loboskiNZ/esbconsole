<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyPortalSchemaCommand extends Command
{
    protected $signature = 'esb:verify-portal-schema
                            {--database=defaultdb : Expected PostgreSQL database name}
                            {--require-pgsql : Fail unless the active connection uses PostgreSQL}';

    protected $description = 'Verify Band Portal canonical schema on the active database connection';

    /**
     * @var array<string, list<string>>
     */
    private const CANONICAL_TABLES = [
        'bands' => ['id', 'public_id', 'name', 'created_at', 'updated_at'],
        'people' => [
            'id', 'public_id', 'band_id', 'legal_first_name', 'legal_middle_names',
            'legal_last_name', 'artistic_name', 'email', 'phone', 'gender', 'pronouns',
            'city', 'country', 'bio', 'profile_photo_path', 'profile_photo_display_path', 'dietary_requirements', 'notes', 'created_at', 'updated_at',
        ],
        'person_secure_fields' => [
            'id', 'person_id', 'field_type', 'encrypted_value', 'encryption_key_context',
            'last_four_preview', 'metadata', 'created_at', 'updated_at',
        ],
        'person_files' => [
            'id', 'person_id', 'file_type', 'file_path', 'original_filename', 'mime_type',
            'size_bytes', 'expires_at', 'notes', 'is_public', 'created_at', 'updated_at',
        ],
        'instrument_reference' => [
            'id', 'public_id', 'slug', 'name', 'family', 'is_active', 'created_at', 'updated_at',
        ],
        'person_instruments' => [
            'id', 'person_id', 'instrument_id', 'role_label', 'is_primary', 'notes',
            'created_at', 'updated_at',
        ],
        'person_iem_settings' => [
            'id', 'person_id', 'name', 'vocal_level', 'own_instrument_level', 'band_level',
            'click_level', 'tracks_level', 'reverb_level', 'ambient_level', 'notes',
            'created_at', 'updated_at',
        ],
        'invite_links' => [
            'id', 'name', 'token_hash', 'token_ciphertext', 'expires_at', 'revoked_at', 'used_count', 'max_uses',
            'created_at', 'updated_at',
        ],
        'invite_link_acceptances' => [
            'id', 'invite_link_id', 'person_id', 'user_id', 'accepted_at', 'created_at', 'updated_at',
        ],
        'users' => [
            'id', 'username', 'person_id', 'band_id', 'password', 'is_active', 'created_at', 'updated_at',
        ],
        'roles' => [
            'id', 'public_id', 'code', 'name', 'description', 'is_system', 'created_at', 'updated_at',
        ],
        'user_roles' => [
            'id', 'user_id', 'role_id', 'band_id', 'assigned_at', 'assigned_by', 'created_at', 'updated_at',
        ],
    ];

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();
        $expectedDatabase = (string) $this->option('database');
        $requirePgsql = $this->option('require-pgsql') || app()->environment('production');

        $this->line('Connection driver: '.$driver);

        if ($requirePgsql && $driver !== 'pgsql') {
            $this->error('Expected PostgreSQL (pgsql) but active connection is '.$driver.'.');

            return self::FAILURE;
        }

        if ($driver === 'pgsql') {
            $currentDatabase = DB::selectOne('select current_database() as name')->name;
            $this->line('Current database: '.$currentDatabase);

            if ($currentDatabase !== $expectedDatabase) {
                $this->error("Expected database {$expectedDatabase} but connected to {$currentDatabase}.");

                return self::FAILURE;
            }
        }

        $failures = 0;

        foreach (self::CANONICAL_TABLES as $table => $requiredColumns) {
            if (! Schema::hasTable($table)) {
                $this->error("Missing table: {$table}");
                $failures++;

                continue;
            }

            $existingColumns = Schema::getColumnListing($table);
            $missingColumns = array_values(array_diff($requiredColumns, $existingColumns));

            if ($missingColumns !== []) {
                $this->error("{$table} missing columns: ".implode(', ', $missingColumns));
                $failures++;
            } else {
                $this->info("OK {$table}");
            }
        }

        if ($failures > 0) {
            $this->error("Schema verification failed ({$failures} issue(s)).");

            return self::FAILURE;
        }

        if ($driver === 'pgsql') {
            $tables = DB::select(
                "select table_name from information_schema.tables where table_schema = 'public' order by table_name"
            );
            $this->line('');
            $this->line('public tables:');
            foreach ($tables as $row) {
                $this->line('  '.$row->table_name);
            }
        }

        $this->info('Band Portal canonical schema verified.');

        return self::SUCCESS;
    }
}
