<?php

// Package: CCMM-05 Music Library
// Authority: PH059 A11–A12, A28
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230, PH010.01
// Notes: song_code + cue_number canonical runtime identity. status column per PH059 (not lifecycle_state).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->char('song_code', 3);
            $table->string('name');
            $table->smallInteger('bpm')->nullable();
            $table->foreignId('time_signature_id')->nullable()->constrained('time_signatures')->nullOnDelete();
            $table->foreignId('musical_key_id')->nullable()->constrained('musical_keys')->nullOnDelete();
            $table->foreignId('mood_id')->nullable()->constrained('song_moods')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('director_notes')->nullable();
            $table->string('status')->default('draft');
            $table->string('genre', 100)->nullable();
            $table->string('style', 100)->nullable();
            $table->string('tempo_feel', 100)->nullable();
            $table->smallInteger('count_in')->nullable();
            $table->text('mood_intention')->nullable();
            $table->text('performance_feel')->nullable();
            $table->text('arrangement_comments')->nullable();
            $table->string('reference_url', 2048)->nullable();
            $table->string('reference_title')->nullable();
            $table->text('reference_notes')->nullable();
            $table->timestamps();

            $table->unique(['band_id', 'song_code']);
            $table->index(['band_id', 'status']);
        });

        Schema::create('cues', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('song_id')->constrained()->restrictOnDelete();
            $table->char('cue_number', 3);
            $table->unsignedSmallInteger('sequence_order')->default(0);
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['song_id', 'cue_number']);
            $table->index(['song_id', 'cue_number']);
            $table->index(['song_id', 'sequence_order']);
        });

        Schema::create('instrument_parts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['band_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_parts');
        Schema::dropIfExists('cues');
        Schema::dropIfExists('songs');
    }
};
