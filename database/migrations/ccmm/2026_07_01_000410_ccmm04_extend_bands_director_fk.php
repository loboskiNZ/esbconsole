<?php

// Package: CCMM-04 Identity & Roster
// Authority: PH059 A1
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: bands.primary_director_musician_id FK after musicians table exists.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->foreign('primary_director_musician_id')
                ->references('id')
                ->on('musicians')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->dropForeign(['primary_director_musician_id']);
        });
    }
};
