<?php

// Package: CCMM-04 Identity & Roster
// Authority: PH059 A2, A7–A8
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230, PH060 users DRIFTED
// Notes: Merged users CREATE on fresh Cloud incl. public_id. Case-insensitive username unique on PostgreSQL.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('username', 32)->nullable();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('band_id')->nullable()->constrained('bands')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX users_username_lower_unique ON users (LOWER(username)) WHERE username IS NOT NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        }

        Schema::create('musicians', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('display_name');
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->text('dietary_preferences')->nullable();
            $table->text('allergies')->nullable();
            $table->text('accessibility_notes')->nullable();
            $table->text('travel_notes')->nullable();
            $table->text('emergency_contact_notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['band_id', 'active']);
        });

        Schema::create('musician_band_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('musician_id')->constrained()->cascadeOnDelete();
            $table->string('role', 64);
            $table->timestamps();

            $table->unique(['musician_id', 'role']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_username_lower_unique');
        }

        Schema::dropIfExists('musician_band_roles');
        Schema::dropIfExists('musicians');
        Schema::dropIfExists('users');
    }
};
