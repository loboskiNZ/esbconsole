<?php

// Package: CCMM-09 Devices & Assignments
// Authority: PH059 A25–A27
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: Musician devices — not integration_devices (LS-EXT).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('musician_id')->constrained()->restrictOnDelete();
            $table->string('device_name');
            $table->string('device_type');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['musician_id', 'active']);
        });

        Schema::create('capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('musician_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_part_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['musician_id', 'instrument_part_id']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('musician_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_part_id')->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['musician_id', 'active']);
            $table->index(['instrument_part_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('capabilities');
        Schema::dropIfExists('devices');
    }
};
