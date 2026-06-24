<?php

// Package: CCMM-08 Shows & Performances
// Authority: PH059 A20–A22
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: ableton_show_file_id required and unique per show (PH059 A21).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ableton_show_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('storage_reference');
            $table->string('checksum');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('band_id');
        });

        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->foreignId('ableton_show_file_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('lifecycle_state')->default('draft');
            $table->timestamps();

            $table->unique('ableton_show_file_id');
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
            $table->unique(['show_id', 'song_id']);
            $table->index(['show_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_playlist_items');
        Schema::dropIfExists('shows');
        Schema::dropIfExists('ableton_show_files');
    }
};
