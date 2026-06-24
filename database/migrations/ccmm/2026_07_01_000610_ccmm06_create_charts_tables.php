<?php

// Package: CCMM-06 Charts & Import Audit
// Authority: PH059 A16–A17
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230, PH054
// Notes: Charts link to songs (PH028 shared chart model). import_batch_id FK enforced.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('song_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('original_filename')->nullable();
            $table->string('storage_reference');
            $table->string('checksum');
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->timestamps();

            $table->unique(['song_id', 'checksum']);
        });

        Schema::create('song_instrument_parts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('song_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_part_id')->constrained()->restrictOnDelete();
            $table->foreignId('chart_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['song_id', 'instrument_part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_instrument_parts');
        Schema::dropIfExists('charts');
    }
};
