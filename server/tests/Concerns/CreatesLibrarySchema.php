<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait CreatesLibrarySchema
{
    protected function createLibrarySchema(): void
    {
        if (Schema::hasTable('songs')) {
            return;
        }

        Schema::create('songs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->default(Str::uuid()->toString());
            $table->unsignedBigInteger('band_id');
            $table->char('song_code', 3)->default('001');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('instrument_parts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->default(Str::uuid()->toString());
            $table->unsignedBigInteger('band_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('charts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->default(Str::uuid()->toString());
            $table->unsignedBigInteger('song_id');
            $table->string('title');
            $table->string('original_filename')->nullable();
            $table->string('storage_reference');
            $table->string('checksum');
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('song_instrument_parts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->default(Str::uuid()->toString());
            $table->unsignedBigInteger('song_id');
            $table->unsignedBigInteger('instrument_part_id');
            $table->unsignedBigInteger('chart_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
