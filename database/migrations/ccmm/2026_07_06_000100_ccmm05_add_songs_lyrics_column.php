<?php

// CCMM Package: CCMM-05
// Decision Reference: DECISION_LOG PH072
// PH Reference: PH059 A11–A12, PH062 §2

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('songs')) {
            return;
        }

        Schema::table('songs', function (Blueprint $table): void {
            if (! Schema::hasColumn('songs', 'lyrics')) {
                $table->text('lyrics')->nullable()->after('director_notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('songs')) {
            return;
        }

        Schema::table('songs', function (Blueprint $table): void {
            if (Schema::hasColumn('songs', 'lyrics')) {
                $table->dropColumn('lyrics');
            }
        });
    }
};
