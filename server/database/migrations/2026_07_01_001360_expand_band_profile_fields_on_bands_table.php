<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bands')) {
            return;
        }

        Schema::table('bands', function (Blueprint $table): void {
            $this->addStringColumn($table, 'short_name', 'name');
            $this->addStringColumn($table, 'tagline', 'short_name');
            $this->addStringColumn($table, 'hometown', 'tagline');

            if (! Schema::hasColumn('bands', 'formation_year')) {
                $table->unsignedSmallInteger('formation_year')->nullable()->after('hometown');
            }

            $this->addTextColumn($table, 'short_bio', 'bio');
            $this->addTextColumn($table, 'full_bio', 'short_bio');

            $this->addStringColumn($table, 'booking_email', 'photo_path');
            $this->addStringColumn($table, 'booking_phone', 'booking_email');
            $this->addStringColumn($table, 'website_url', 'booking_phone');
            $this->addStringColumn($table, 'facebook_url', 'website_url');
            $this->addStringColumn($table, 'instagram_url', 'facebook_url');
            $this->addStringColumn($table, 'tiktok_url', 'instagram_url');
            $this->addStringColumn($table, 'youtube_url', 'tiktok_url');
            $this->addStringColumn($table, 'spotify_url', 'youtube_url');
            $this->addStringColumn($table, 'apple_music_url', 'spotify_url');
            $this->addStringColumn($table, 'bandcamp_url', 'apple_music_url');
            $this->addStringColumn($table, 'press_photo_path', 'bandcamp_url');
            $this->addStringColumn($table, 'hero_photo_path', 'press_photo_path');
        });
    }

    public function down(): void
    {
        // Non-destructive — expanded band profile columns retained per PH072.
    }

    private function addStringColumn(Blueprint $table, string $column, string $after): void
    {
        if (! Schema::hasColumn('bands', $column)) {
            $table->string($column)->nullable()->after($after);
        }
    }

    private function addTextColumn(Blueprint $table, string $column, string $after): void
    {
        if (! Schema::hasColumn('bands', $column)) {
            $table->text($column)->nullable()->after($after);
        }
    }
};
