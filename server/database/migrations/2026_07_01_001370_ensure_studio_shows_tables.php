<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ableton_show_files')) {
            Schema::create('ableton_show_files', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('band_id')->constrained('bands')->restrictOnDelete();
                $table->string('name');
                $table->string('storage_reference');
                $table->string('checksum');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('band_id');
            });
        }

        if (! Schema::hasTable('shows')) {
            Schema::create('shows', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('band_id')->constrained('bands')->restrictOnDelete();
                $table->foreignId('ableton_show_file_id')->constrained('ableton_show_files')->restrictOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('lifecycle_state')->default('draft');
                $table->timestamp('scheduled_at')->nullable();
                $table->string('venue_location')->nullable();
                $table->timestamps();

                $table->unique('ableton_show_file_id');
                $table->index(['band_id', 'lifecycle_state']);
                $table->index(['band_id', 'scheduled_at']);
            });

            return;
        }

        Schema::table('shows', function (Blueprint $table): void {
            if (! Schema::hasColumn('shows', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('lifecycle_state');
            }

            if (! Schema::hasColumn('shows', 'venue_location')) {
                $table->string('venue_location')->nullable()->after('scheduled_at');
            }
        });
    }

    public function down(): void
    {
        // Non-destructive — show tables and columns retained per PH072.
    }
};
