<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('song_moods')) {
            Schema::create('song_moods', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('colour_hex', 7);
                $table->string('accent_colour_hex', 7);
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('time_signatures')) {
            Schema::create('time_signatures', function (Blueprint $table): void {
                $table->id();
                $table->string('label');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique('label');
            });
        }

        if (! Schema::hasTable('musical_keys')) {
            Schema::create('musical_keys', function (Blueprint $table): void {
                $table->id();
                $table->string('label');
                $table->string('tonic', 4);
                $table->string('mode', 16);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique('label');
            });
        }

        Schema::table('songs', function (Blueprint $table): void {
            if (! Schema::hasColumn('songs', 'time_signature_id')) {
                $table->foreignId('time_signature_id')->nullable()->after('bpm')->constrained('time_signatures')->nullOnDelete();
            }

            if (! Schema::hasColumn('songs', 'musical_key_id')) {
                $table->foreignId('musical_key_id')->nullable()->after('time_signature_id')->constrained('musical_keys')->nullOnDelete();
            }

            if (! Schema::hasColumn('songs', 'mood_id')) {
                $table->foreignId('mood_id')->nullable()->after('musical_key_id')->constrained('song_moods')->nullOnDelete();
            }

            if (! Schema::hasColumn('songs', 'director_notes')) {
                $table->text('director_notes')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            if (Schema::hasColumn('songs', 'time_signature_id')) {
                $table->dropConstrainedForeignId('time_signature_id');
            }

            if (Schema::hasColumn('songs', 'musical_key_id')) {
                $table->dropConstrainedForeignId('musical_key_id');
            }

            if (Schema::hasColumn('songs', 'mood_id')) {
                $table->dropConstrainedForeignId('mood_id');
            }

            if (Schema::hasColumn('songs', 'director_notes')) {
                $table->dropColumn('director_notes');
            }
        });

        Schema::dropIfExists('musical_keys');
        Schema::dropIfExists('time_signatures');
        Schema::dropIfExists('song_moods');
    }
};
