<?php

// Package: CCMM-06 Charts & Import Audit
// Authority: PH059 A33, PH027/PH028
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 224
// Notes: Music snippets only — not X32 console snippets. Partial unique index for active rows.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snippets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('song_instrument_part_id')->constrained()->restrictOnDelete();
            $table->foreignId('cue_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 32)->default('chart_crop');
            $table->foreignId('source_snippet_id')->nullable()->constrained('snippets')->nullOnDelete();
            $table->foreignId('source_chart_id')->nullable()->constrained('charts')->nullOnDelete();
            $table->string('freshness_state', 32)->default('current');
            $table->boolean('is_active')->default(true);
            $table->string('title');
            $table->string('storage_reference');
            $table->string('checksum');
            $table->string('annotation_storage_reference')->nullable();
            $table->string('markup_storage_reference')->nullable();
            $table->string('rendered_storage_reference')->nullable();
            $table->json('source_metadata')->nullable();
            $table->string('chart_revision_at_creation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $activePredicate = DB::getDriverName() === 'pgsql' ? 'is_active IS TRUE' : 'is_active = 1';

        DB::statement(
            "CREATE UNIQUE INDEX snippets_active_sip_cue_unique ON snippets (song_instrument_part_id, cue_id) WHERE {$activePredicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS snippets_active_sip_cue_unique');
        Schema::dropIfExists('snippets');
    }
};
