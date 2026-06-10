<?php

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
            $table->foreignId('song_instrument_part_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('storage_reference');
            $table->string('checksum');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('snippets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('song_instrument_part_id')->constrained()->restrictOnDelete();
            $table->foreignId('cue_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('storage_reference');
            $table->string('checksum');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['song_instrument_part_id', 'cue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snippets');
        Schema::dropIfExists('charts');
    }
};
