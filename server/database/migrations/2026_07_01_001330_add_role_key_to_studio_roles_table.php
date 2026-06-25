<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        if (! Schema::hasColumn('roles', 'role_key')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('role_key', 64)->nullable()->after('public_id');
            });
        }

        if (! Schema::hasColumn('roles', 'code')) {
            return;
        }

        DB::table('roles')
            ->whereNull('role_key')
            ->whereNotNull('code')
            ->orderBy('id')
            ->each(function (object $role): void {
                DB::table('roles')
                    ->where('id', $role->id)
                    ->whereNull('role_key')
                    ->update(['role_key' => $role->code]);
            });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS roles_role_key_unique ON roles (role_key) WHERE role_key IS NOT NULL');
        } elseif (! $this->roleKeyUniqueIndexExists()) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique('role_key');
            });
        }
    }

    public function down(): void
    {
        // Non-destructive — role_key column and backfilled values retained per PH072.
    }

    private function roleKeyUniqueIndexExists(): bool
    {
        $indexes = Schema::getIndexes('roles');

        foreach ($indexes as $index) {
            if (in_array('role_key', $index['columns'], true) && ($index['unique'] ?? false)) {
                return true;
            }
        }

        return false;
    }
};
