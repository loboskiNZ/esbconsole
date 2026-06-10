<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cues', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('song_id')->constrained()->restrictOnDelete();
            $table->char('cue_number', 3);
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['song_id', 'cue_number']);
            $table->index(['song_id', 'cue_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cues');
    }
};
