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

        if (Schema::hasColumn('roles', 'role_key')) {
            $this->ensureRoleKeyUniqueIndex();

            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->string('role_key', 64)->nullable();
        });

        if (Schema::hasColumn('roles', 'code')) {
            DB::table('roles')
                ->whereNull('role_key')
                ->whereNotNull('code')
                ->orderBy('id')
                ->get(['id', 'code'])
                ->each(function (object $role): void {
                    DB::table('roles')
                        ->where('id', $role->id)
                        ->whereNull('role_key')
                        ->update(['role_key' => $role->code]);
                });
        }

        $this->ensureRoleKeyUniqueIndex();
    }

    public function down(): void
    {
        // Non-destructive — role_key column retained per PH072.
    }

    private function ensureRoleKeyUniqueIndex(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS roles_role_key_unique ON roles (role_key) WHERE role_key IS NOT NULL');

            return;
        }

        if ($this->roleKeyUniqueIndexExists()) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->unique('role_key');
        });
    }

    private function roleKeyUniqueIndexExists(): bool
    {
        foreach (Schema::getIndexes('roles') as $index) {
            if (in_array('role_key', $index['columns'], true) && ($index['unique'] ?? false)) {
                return true;
            }
        }

        return false;
    }
};
