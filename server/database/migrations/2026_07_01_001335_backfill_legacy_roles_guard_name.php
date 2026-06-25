<?php

use App\Services\StudioRoleProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'guard_name')) {
            return;
        }

        DB::table('roles')
            ->whereNull('guard_name')
            ->update(['guard_name' => StudioRoleProvisioner::studioRoleGuardName()]);
    }

    public function down(): void
    {
        // Non-destructive — guard_name values retained per PH072.
    }
};
