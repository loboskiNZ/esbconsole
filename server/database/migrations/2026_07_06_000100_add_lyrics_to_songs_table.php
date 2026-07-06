<?php

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
