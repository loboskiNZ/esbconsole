<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            if (! Schema::hasColumn('songs', 'spotify_url')) {
                $table->string('spotify_url', 2048)->nullable();
            }

            if (! Schema::hasColumn('songs', 'youtube_url')) {
                $table->string('youtube_url', 2048)->nullable();
            }
        });

        if (Schema::hasTable('song_assets')) {
            return;
        }

        Schema::create('song_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('song_id');
            $table->string('asset_type', 32);
            $table->string('label');
            $table->string('storage_disk', 32);
            $table->string('storage_reference');
            $table->string('original_filename');
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 64);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['song_id', 'sort_order']);
            $table->index('storage_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_assets');

        Schema::table('songs', function (Blueprint $table): void {
            foreach (['youtube_url', 'spotify_url'] as $column) {
                if (Schema::hasColumn('songs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
