<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyMigrationIssues
{
    /**
     * @param  list<LegacyMissingAsset>  $missingChartFiles
     * @param  list<LegacyMissingAsset>  $missingSnippetFiles
     * @param  list<LegacyUnresolvedRole>  $unresolvedRoles
     * @param  list<string>  $orphanSnippets
     * @param  list<string>  $zeroCueSongs
     * @param  list<string>  $placeholderChartsSkipped
     * @param  list<string>  $warnings
     * @param  list<string>  $blockers
     */
    public function __construct(
        public array $missingChartFiles = [],
        public array $missingSnippetFiles = [],
        public array $unresolvedRoles = [],
        public array $orphanSnippets = [],
        public array $zeroCueSongs = [],
        public array $placeholderChartsSkipped = [],
        public array $warnings = [],
        public array $blockers = [],
    ) {}

    public function toArray(): array
    {
        return [
            'missing_chart_files' => array_map(fn (LegacyMissingAsset $a) => [
                'asset_type' => $a->assetType,
                'legacy_song_id' => $a->legacySongId,
                'legacy_path' => $a->legacyPath,
                'role_slug' => $a->roleSlug,
                'legacy_cue_index' => $a->legacyCueIndex,
            ], $this->missingChartFiles),
            'missing_snippet_files' => array_map(fn (LegacyMissingAsset $a) => [
                'asset_type' => $a->assetType,
                'legacy_song_id' => $a->legacySongId,
                'legacy_path' => $a->legacyPath,
                'role_slug' => $a->roleSlug,
                'legacy_cue_index' => $a->legacyCueIndex,
            ], $this->missingSnippetFiles),
            'unresolved_roles' => array_map(fn (LegacyUnresolvedRole $r) => [
                'legacy_song_id' => $r->legacySongId,
                'legacy_role_slug' => $r->legacyRoleSlug,
                'reason' => $r->reason,
            ], $this->unresolvedRoles),
            'orphan_snippets' => $this->orphanSnippets,
            'zero_cue_songs' => $this->zeroCueSongs,
            'placeholder_charts_skipped' => $this->placeholderChartsSkipped,
            'warnings' => $this->warnings,
            'blockers' => $this->blockers,
        ];
    }
}
