<?php

// Package: CCMM-06 Charts & Import Audit
// Authority: PH059 A18–A19
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: import_batches must exist before charts.import_batch_id FK.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('legacy_setlist_id');
            $table->string('status')->default('dry_run');
            $table->json('manifest_json')->nullable();
            $table->json('report_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['band_id', 'status']);
        });

        Schema::create('import_entity_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->string('legacy_key');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->uuid('public_id')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'entity_type', 'legacy_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_entity_mappings');
        Schema::dropIfExists('import_batches');
    }
};
