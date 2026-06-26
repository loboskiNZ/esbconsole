<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('show_playlist_items')) {
            return;
        }

        Schema::table('show_playlist_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('show_playlist_items', 'notes')) {
                $table->text('notes')->nullable();
            }

            if (! Schema::hasColumn('show_playlist_items', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    public function down(): void
    {
        // Non-destructive — playlist columns retained per PH072.
    }
};
