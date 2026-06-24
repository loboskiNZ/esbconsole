<?php

// Package: CCMM-10 Venues & Festivals
// Authority: PH059 A34–A35
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: Shared canonical tour/operations entities.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('facebook_tag')->nullable();
            $table->string('instagram_tag')->nullable();
            $table->string('tiktok_tag')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['band_id', 'active']);
            $table->index(['band_id', 'name']);
        });

        Schema::create('festivals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('application_url')->nullable();
            $table->date('application_deadline')->nullable();
            $table->text('festival_date_notes')->nullable();
            $table->string('application_status', 32)->default('not_applied');
            $table->string('facebook_tag')->nullable();
            $table->string('instagram_tag')->nullable();
            $table->string('tiktok_tag')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['band_id', 'active']);
            $table->index(['band_id', 'name']);
            $table->index('application_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festivals');
        Schema::dropIfExists('venues');
    }
};
