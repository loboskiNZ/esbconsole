<?php

// Package: CCMM-02 Reference Data
// Authority: PH059 A9, A13–A15
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: Reference seeds applied in F2 (InstrumentCatalog, SongMetadataReferenceSeeder).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_reference', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('family')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('song_moods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->char('colour_hex', 7);
            $table->char('accent_colour_hex', 7);
            $table->text('description')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('time_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('musical_keys', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->string('tonic', 4);
            $table->string('mode', 16);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('musical_keys');
        Schema::dropIfExists('time_signatures');
        Schema::dropIfExists('song_moods');
        Schema::dropIfExists('instrument_reference');
    }
};
