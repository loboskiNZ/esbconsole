<?php

// Package: CCMM stabilisation additive
// Authority: practical Cloud DB stabilisation
// Notes: Creates only missing shared tables. Safe on existing Cloud databases.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('person_invitations')) {
            Schema::create('person_invitations', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
                $table->string('token_hash')->unique();
                $table->timestamp('expires_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->string('status');
                $table->timestamps();

                $table->index(['person_id', 'status']);
                $table->index('expires_at');
            });
        }

        if (! Schema::hasTable('cloud_recovery_entity_map')) {
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
        // Non-destructive stabilisation — no down() drops on production Cloud DB.
    }
};
