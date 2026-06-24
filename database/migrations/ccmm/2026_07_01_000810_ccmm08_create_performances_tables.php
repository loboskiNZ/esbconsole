<?php

// Package: CCMM-08 Shows & Performances
// Authority: PH059 A23–A24
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: performance_assignments link performances to musicians and optional song/cue scope.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->foreignId('show_id')->constrained()->restrictOnDelete();
            $table->string('venue');
            $table->date('performance_date');
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['band_id', 'status']);
            $table->index(['show_id', 'performance_date']);
        });

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
        Schema::dropIfExists('performances');
    }
};
