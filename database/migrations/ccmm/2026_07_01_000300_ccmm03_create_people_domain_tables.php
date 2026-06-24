<?php

// Package: CCMM-03 People
// Authority: PH059 A3–A6, A10
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: Shared ESB schema; Cloud-first reliability authority; Live Stage parity required.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('legal_first_name');
            $table->string('legal_middle_names')->nullable();
            $table->string('legal_last_name');
            $table->string('artistic_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->string('pronouns')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->text('dietary_requirements')->nullable();
            $table->text('notes')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('profile_photo_display_path')->nullable();
            $table->timestamps();

            $table->index(['band_id', 'legal_last_name', 'legal_first_name']);
        });

        Schema::create('person_secure_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('field_type', 64);
            $table->text('encrypted_value');
            $table->string('encryption_key_context', 128);
            $table->string('last_four_preview', 16)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'field_type']);
        });

        Schema::create('person_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('file_type', 64);
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['person_id', 'file_type']);
            $table->index('is_public');
        });

        Schema::create('person_iem_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('vocal_level', 5, 2)->nullable();
            $table->decimal('own_instrument_level', 5, 2)->nullable();
            $table->decimal('band_level', 5, 2)->nullable();
            $table->decimal('click_level', 5, 2)->nullable();
            $table->decimal('tracks_level', 5, 2)->nullable();
            $table->decimal('reverb_level', 5, 2)->nullable();
            $table->decimal('ambient_level', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'name']);
        });

        Schema::create('person_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instrument_reference')->restrictOnDelete();
            $table->string('role_label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'instrument_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_instruments');
        Schema::dropIfExists('person_iem_settings');
        Schema::dropIfExists('person_files');
        Schema::dropIfExists('person_secure_fields');
        Schema::dropIfExists('people');
    }
};
