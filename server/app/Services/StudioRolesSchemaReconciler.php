<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StudioRolesSchemaReconciler
{
    public function reconcile(): void
    {
        if (! Schema::hasTable('roles')) {
            $this->createRolesTable();

            return;
        }

        $this->ensureRolesColumns();
        $this->backfillMissingRolePublicIds();
        $this->backfillRoleKeyFromLegacyCode();
        $this->ensureRoleIndexes();

        if (! Schema::hasTable('user_roles')) {
            $this->createUserRolesTable();

            return;
        }

        $this->ensureUserRolesColumns();
    }

    private function createRolesTable(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('role_key', 64)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });
    }

    private function createUserRolesTable(): void
    {
        Schema::create('user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('band_id')->nullable()->constrained('bands')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'band_id']);
            $table->index(['role_id', 'band_id']);
        });
    }

    private function ensureRolesColumns(): void
    {
        if (! Schema::hasColumn('roles', 'public_id')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->uuid('public_id')->nullable();
            });
        }

        if (! Schema::hasColumn('roles', 'role_key')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->string('role_key', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('roles', 'description')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->text('description')->nullable();
            });
        }

        if (! Schema::hasColumn('roles', 'is_system')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->boolean('is_system')->default(true);
            });
        }

        if (! Schema::hasColumn('roles', 'created_at')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasColumn('roles', 'updated_at')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    private function ensureUserRolesColumns(): void
    {
        if (! Schema::hasColumn('user_roles', 'band_id')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->foreignId('band_id')->nullable()->constrained('bands')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('user_roles', 'assigned_at')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->timestamp('assigned_at')->nullable();
            });

            DB::table('user_roles')
                ->whereNull('assigned_at')
                ->update(['assigned_at' => now()]);
        }

        if (! Schema::hasColumn('user_roles', 'assigned_by')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('user_roles', 'created_at')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasColumn('user_roles', 'updated_at')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    private function backfillMissingRolePublicIds(): void
    {
        DB::table('roles')
            ->whereNull('public_id')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $roleId): void {
                DB::table('roles')
                    ->where('id', $roleId)
                    ->whereNull('public_id')
                    ->update(['public_id' => (string) Str::uuid()]);
            });
    }

    private function backfillRoleKeyFromLegacyCode(): void
    {
        if (! Schema::hasColumn('roles', 'code') || ! Schema::hasColumn('roles', 'role_key')) {
            return;
        }

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

    private function ensureRoleIndexes(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS roles_public_id_unique ON roles (public_id) WHERE public_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS roles_role_key_unique ON roles (role_key) WHERE role_key IS NOT NULL');

            return;
        }

        if (! $this->hasUniqueIndex('roles', 'public_id')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->unique('public_id');
            });
        }

        if (! $this->hasUniqueIndex('roles', 'role_key')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->unique('role_key');
            });
        }
    }

    private function hasUniqueIndex(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (in_array($column, $index['columns'], true) && ($index['unique'] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
