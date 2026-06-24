<?php

// Package: CCMM-01 Foundation
// Authority: PH059 A1
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: primary_director_musician_id FK added in CCMM-04 after musicians exist.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bands', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->unsignedBigInteger('primary_director_musician_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bands');
    }
};
