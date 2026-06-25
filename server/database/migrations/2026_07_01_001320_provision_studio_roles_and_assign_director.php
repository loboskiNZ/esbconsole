<?php

use App\Services\StudioRoleProvisioner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(StudioRoleProvisioner::class)) {
            return;
        }

        app(StudioRoleProvisioner::class)->provision();
    }

    public function down(): void
    {
        // Non-destructive — seeded roles and assignments retained per PH072.
    }
};
