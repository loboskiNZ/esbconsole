<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('lifecycle_state')->default('draft');
            $table->timestamps();

            $table->index(['band_id', 'lifecycle_state']);
        });

        Schema::create('show_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->restrictOnDelete();
            $table->foreignId('song_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedSmallInteger('ableton_pgm')->nullable();
            $table->timestamps();

            $table->unique(['show_id', 'position']);
            $table->index(['show_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_playlist_items');
        Schema::dropIfExists('shows');
    }
};
