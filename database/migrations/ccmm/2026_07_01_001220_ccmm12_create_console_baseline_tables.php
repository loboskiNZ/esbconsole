<?php

// Package: CCMM-12 X32 Console Configuration
// Authority: PH061A
// Authoring Plan: PH062
// Decision Reference: PH061A 220, 221, PH062 233
// Notes: baseline_json stores channel/bus/routing configuration (PH043). source_snapshot_id has no FK — LS learning table is LS-EXT.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_console_baselines', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->foreignId('show_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('source_snapshot_id')->nullable();
            $table->string('baseline_name');
            $table->string('console_type', 16);
            $table->json('baseline_json');
            $table->boolean('active')->default(true);
            $table->timestamp('saved_at');
            $table->timestamps();

            $table->index(['show_id', 'active']);
            $table->index(['band_id', 'show_id']);
        });

        if (! Schema::hasTable('mix_moves')) {
            Schema::create('mix_moves', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mix_moves');
        Schema::dropIfExists('show_console_baselines');
    }
};
