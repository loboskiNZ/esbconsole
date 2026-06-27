<?php

namespace App\Support;

class SongAssetType
{
    public const AUDIO = 'audio';

    public const MIDI = 'midi';

    public const STEM = 'stem';

    public const BACKING_TRACK = 'backing_track';

    public const REFERENCE = 'reference';

    public const DEMO = 'demo';

    public const OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::AUDIO,
            self::MIDI,
            self::STEM,
            self::BACKING_TRACK,
            self::REFERENCE,
            self::DEMO,
            self::OTHER,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::AUDIO => 'Audio',
            self::MIDI => 'MIDI',
            self::STEM => 'Stem',
            self::BACKING_TRACK => 'Backing track',
            self::REFERENCE => 'Reference',
            self::DEMO => 'Demo',
            self::OTHER => 'Other',
        ];
    }

    public static function labelFor(string $type): string
    {
        return self::labels()[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function inferFromFilename(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'mid', 'midi' => self::MIDI,
            'wav' => self::STEM,
            'mp3' => self::AUDIO,
            default => self::OTHER,
        };
    }
}
