<?php

// Package: RECOVERY
// Authority: PH061 §5.2
// Authoring Plan: PH062
// Decision Reference: PH061 231, PH062
// Notes: Cloud recovery audit table for governed data import ID remap. Cloud Extension — not Live Stage parity.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_recovery_entity_map', function (Blueprint $table) {
            $table->id();
            $table->string('source_env');
            $table->string('table_name');
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('cloud_id');
            $table->uuid('public_id')->nullable();
            $table->timestamp('migrated_at');
            $table->uuid('batch_id');
            $table->timestamps();

            $table->index(['table_name', 'batch_id']);
            $table->unique(['source_env', 'table_name', 'source_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_recovery_entity_map');
    }
};
