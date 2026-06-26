<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_assignments')) {
            return;
        }

        Schema::table('performance_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('performance_assignments', 'responded_at')) {
                $table->timestamp('responded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non-destructive — responded_at retained per PH072.
    }
};
