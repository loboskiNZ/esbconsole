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
     *     genre: ?string,
     *     style: ?string,
     *     tempo_feel: ?string,
     *     count_in: ?int,
     *     director_notes: ?string,
     *     mood_intention: ?string,
     *     performance_feel: ?string,
     *     arrangement_comments: ?string,
     *     reference_title: ?string,
     *     reference_url: ?string,
     *     has_metadata: bool,
     *     has_brief: bool
     * }
     */
    public function forSong(Song $song): array
    {
        $song->loadMissing(['timeSignature', 'musicalKey', 'mood']);

        /** @var SongMood|null $mood */
        $mood = $song->mood;

        $hasBrief = filled($song->director_notes)
            || filled($song->mood_intention)
            || filled($song->performance_feel)
            || filled($song->arrangement_comments);

        return [
            'bpm' => $song->bpm !== null ? (int) $song->bpm : null,
            'time_signature' => $song->timeSignature?->label,
            'musical_key' => $song->musicalKey?->label,
            'mood_label' => $mood?->name ?? self::DEFAULT_MOOD_LABEL,
            'mood_colour_hex' => $mood?->colour_hex ?? self::DEFAULT_MOOD_COLOUR,
            'mood_accent_colour_hex' => $mood?->accent_colour_hex ?? self::DEFAULT_MOOD_ACCENT,
            'genre' => $song->genre,
            'style' => $song->style,
            'tempo_feel' => $song->tempo_feel,
            'count_in' => $song->count_in !== null ? (int) $song->count_in : null,
            'director_notes' => $song->director_notes,
            'mood_intention' => $song->mood_intention,
            'performance_feel' => $song->performance_feel,
            'arrangement_comments' => $song->arrangement_comments,
            'reference_title' => $song->reference_title,
            'reference_url' => $song->reference_url,
            'has_metadata' => $song->bpm !== null
                || $song->timeSignature !== null
                || $song->musicalKey !== null
                || filled($song->genre)
                || filled($song->style)
                || filled($song->tempo_feel)
                || $song->count_in !== null,
            'has_brief' => $hasBrief,
        ];
    }
}
