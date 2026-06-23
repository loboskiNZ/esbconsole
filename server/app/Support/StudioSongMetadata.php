<?php

namespace App\Support;

use App\Models\Library\Song;
use App\Models\Library\SongMood;

class StudioSongMetadata
{
    public const DEFAULT_MOOD_LABEL = 'Neutral';

    public const DEFAULT_MOOD_COLOUR = '#5BC0EB';

    public const DEFAULT_MOOD_ACCENT = '#8ED4F0';

    /**
     * @return array{
     *     bpm: ?int,
     *     time_signature: ?string,
     *     musical_key: ?string,
     *     mood_label: string,
     *     mood_colour_hex: string,
     *     mood_accent_colour_hex: string,
     *     director_notes: ?string,
     *     has_metadata: bool
     * }
     */
    public function forSong(Song $song): array
    {
        $song->loadMissing(['timeSignature', 'musicalKey', 'mood']);

        /** @var SongMood|null $mood */
        $mood = $song->mood;

        return [
            'bpm' => $song->bpm !== null ? (int) $song->bpm : null,
            'time_signature' => $song->timeSignature?->label,
            'musical_key' => $song->musicalKey?->label,
            'mood_label' => $mood?->name ?? self::DEFAULT_MOOD_LABEL,
            'mood_colour_hex' => $mood?->colour_hex ?? self::DEFAULT_MOOD_COLOUR,
            'mood_accent_colour_hex' => $mood?->accent_colour_hex ?? self::DEFAULT_MOOD_ACCENT,
            'director_notes' => $song->director_notes,
            'has_metadata' => $song->bpm !== null
                || $song->timeSignature !== null
                || $song->musicalKey !== null
                || filled($song->director_notes),
        ];
    }
}
