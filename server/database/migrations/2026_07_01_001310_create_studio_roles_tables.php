<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('role_key', 64)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
                $table->foreignId('band_id')->nullable()->constrained('bands')->nullOnDelete();
                $table->timestamp('assigned_at');
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'role_id', 'band_id']);
                $table->index(['role_id', 'band_id']);
            });
        }
    }

    public function down(): void
    {
        // Non-destructive — role tables retained per PH072.
    }
};
