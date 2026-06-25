<?php

use App\Services\StudioRolesSchemaReconciler;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(StudioRolesSchemaReconciler::class)) {
            return;
        }

        app(StudioRolesSchemaReconciler::class)->reconcile();
    }

    public function down(): void
    {
        // Non-destructive — reconciled columns retained per PH072.
    }
};
