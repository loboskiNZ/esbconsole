<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instrument_parts')) {
            Schema::create('instrument_parts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('band_id')->constrained('bands')->restrictOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index(['band_id', 'active']);
            });
        }

        if (! Schema::hasTable('songs')) {
            Schema::create('songs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('band_id')->constrained('bands')->restrictOnDelete();
                $table->char('song_code', 3);
                $table->string('name');
                $table->unsignedSmallInteger('bpm')->nullable();
                $table->text('description')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('draft');
                $table->timestamps();

                $table->unique(['band_id', 'song_code']);
                $table->index(['band_id', 'status']);
            });
        }

        if (! Schema::hasTable('charts')) {
            Schema::create('charts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('song_id')->constrained('songs')->restrictOnDelete();
                $table->string('title');
                $table->string('original_filename')->nullable();
                $table->string('storage_reference');
                $table->string('checksum');
                $table->string('mime_type', 127)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('import_batch_id')->nullable();
                $table->timestamps();

                $table->unique(['song_id', 'checksum']);
            });
        }

        if (! Schema::hasTable('song_instrument_parts')) {
            Schema::create('song_instrument_parts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('song_id')->constrained('songs')->restrictOnDelete();
                $table->foreignId('instrument_part_id')->constrained('instrument_parts')->restrictOnDelete();
                $table->foreignId('chart_id')->nullable()->constrained('charts')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['song_id', 'instrument_part_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('song_instrument_parts');
        Schema::dropIfExists('charts');
        Schema::dropIfExists('songs');
        Schema::dropIfExists('instrument_parts');
    }
};
