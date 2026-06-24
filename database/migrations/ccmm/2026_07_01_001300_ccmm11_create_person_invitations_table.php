<?php

// Package: CCMM-11 Invitations
// Authority: PH059 Part C
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 232, PH048B
// Notes: Cloud workspace only — excluded from Live Stage parity apply. Person-first onboarding.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('person_invitations');
    }
};
