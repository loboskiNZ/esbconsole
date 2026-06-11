<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyMigrationPlan
{
    /**
     * @param  list<LegacySongCandidate>  $songs
     * @param  list<LegacyPlaylistItemCandidate>  $playlistItems
     * @param  list<LegacyCueCandidate>  $cues
     * @param  list<LegacyInstrumentPartCandidate>  $instrumentParts
     * @param  list<LegacySongInstrumentPartCandidate>  $songInstrumentParts
     * @param  list<LegacyChartCandidate>  $charts
     * @param  list<LegacySnippetCandidate>  $snippets
     * @param  list<LegacyMusicianCandidate>  $musicians
     */
    public function __construct(
        public string $importBatchId,
        public LegacyShowCandidate $show,
        public array $songs,
        public array $playlistItems,
        public array $cues,
        public array $instrumentParts,
        public array $songInstrumentParts,
        public array $charts,
        public array $snippets,
        public array $musicians,
        public LegacyMigrationIssues $issues,
        public array $legacyIdMappings,
    ) {}

    public function toManifestArray(): array
    {
        return [
            'import_batch_id' => $this->importBatchId,
            'legacy_setlist_id' => $this->show->legacySetlistId,
            'show_name' => $this->show->name,
            'songs' => array_map(fn (LegacySongCandidate $s) => [
                'legacy_song_id' => $s->legacySongId,
                'song_code' => $s->songCode,
                'title' => $s->title,
                'playlist_position' => $s->playlistPosition,
            ], $this->songs),
            'cues' => array_map(fn (LegacyCueCandidate $c) => [
                'legacy_song_id' => $c->legacySongId,
                'legacy_cue_index' => $c->legacyCueIndex,
                'cue_number' => $c->cueNumber,
                'sequence_order' => $c->sequenceOrder,
                'synthetic_preparation' => $c->syntheticPreparation,
                'name' => $c->name,
            ], $this->cues),
            'issues' => $this->issues->toArray(),
            'legacy_id_mappings' => $this->legacyIdMappings,
        ];
    }
}
