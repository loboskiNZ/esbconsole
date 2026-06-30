<?php

namespace Tests\Unit;

use App\Models\Library\Song;
use App\Models\ShowPlaylistItem;
use App\Services\StudioShowSetlistPdfService;
use ReflectionMethod;
use Tests\TestCase;

class StudioShowSetlistPdfServiceTest extends TestCase
{
    public function test_setlist_notes_include_playlist_and_song_notes_with_matching_labels(): void
    {
        $item = new ShowPlaylistItem([
            'notes' => "Gat chords:\nIntro: A",
        ]);

        $song = new Song([
            'notes' => "Intro starts with percussion only.\nLeave space before verse.",
        ]);

        $formatted = $this->formatSetlistNotes($item, $song);

        $this->assertSame(
            "Song notes:\nIntro starts with percussion only.\nLeave space before verse.\n\nNotes:\nGat chords:\nIntro: A",
            $formatted,
        );
    }

    public function test_setlist_notes_include_only_playlist_notes_when_song_notes_empty(): void
    {
        $item = new ShowPlaylistItem([
            'notes' => 'Walk-on energy',
        ]);

        $formatted = $this->formatSetlistNotes($item, new Song());

        $this->assertSame("Notes:\nWalk-on energy", $formatted);
    }

    public function test_setlist_notes_include_only_song_notes_when_playlist_notes_empty(): void
    {
        $song = new Song([
            'notes' => 'Canonical arrangement reminder.',
        ]);

        $formatted = $this->formatSetlistNotes(new ShowPlaylistItem(), $song);

        $this->assertSame("Song notes:\nCanonical arrangement reminder.", $formatted);
    }

    public function test_setlist_notes_are_empty_when_both_sources_are_blank(): void
    {
        $formatted = $this->formatSetlistNotes(new ShowPlaylistItem(), new Song());

        $this->assertSame('', $formatted);
    }

    private function formatSetlistNotes(ShowPlaylistItem $item, ?Song $song): string
    {
        $method = new ReflectionMethod(StudioShowSetlistPdfService::class, 'notesForSetlistRow');
        $method->setAccessible(true);

        return $method->invoke(app(StudioShowSetlistPdfService::class), $item, $song);
    }
}
