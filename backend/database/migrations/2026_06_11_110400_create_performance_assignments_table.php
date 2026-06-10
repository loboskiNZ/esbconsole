<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->foreignId('musician_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_part_id')->constrained()->restrictOnDelete();
            $table->foreignId('song_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cue_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['performance_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_assignments');
    }
};
