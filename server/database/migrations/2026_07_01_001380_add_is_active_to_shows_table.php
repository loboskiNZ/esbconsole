<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shows') || Schema::hasColumn('shows', 'is_active')) {
            return;
        }

        Schema::table('shows', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('lifecycle_state');
        });
    }

    public function down(): void
    {
        // Non-destructive — is_active retained per PH072.
    }
};
