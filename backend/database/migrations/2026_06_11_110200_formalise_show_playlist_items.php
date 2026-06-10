<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('show_playlist_items', function (Blueprint $table) {
            $table->unique(['show_id', 'song_id']);
        });
    }

    public function down(): void
    {
        Schema::table('show_playlist_items', function (Blueprint $table) {
            $table->dropUnique(['show_id', 'song_id']);
        });
    }
};
