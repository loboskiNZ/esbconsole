<?php

namespace App\Services\LegacyImport;

use App\DataTransferObjects\LegacyImport\LegacyChartCandidate;
use App\DataTransferObjects\LegacyImport\LegacyCueCandidate;
use App\DataTransferObjects\LegacyImport\LegacyImportConfig;
use App\DataTransferObjects\LegacyImport\LegacyInstrumentPartCandidate;
use App\DataTransferObjects\LegacyImport\LegacyMigrationIssues;
use App\DataTransferObjects\LegacyImport\LegacyMigrationPlan;
use App\DataTransferObjects\LegacyImport\LegacyMissingAsset;
use App\DataTransferObjects\LegacyImport\LegacyMusicianCandidate;
use App\DataTransferObjects\LegacyImport\LegacyPlaylistItemCandidate;
use App\DataTransferObjects\LegacyImport\LegacyShowCandidate;
use App\DataTransferObjects\LegacyImport\LegacySnippetCandidate;
use App\DataTransferObjects\LegacyImport\LegacySongCandidate;
use App\DataTransferObjects\LegacyImport\LegacySongInstrumentPartCandidate;
use App\Models\Snippet;
use Illuminate\Support\Str;

class LegacyMigrationPlanBuilder
{
    public function __construct(
        private readonly LegacySetlistLoader $setlistLoader,
        private readonly LegacyRoleNormalizer $roleNormalizer,
    ) {}

    public function build(LegacyImportConfig $config): LegacyMigrationPlan
    {
        $pathResolver = new LegacyPathResolver($config);
        $bandSlug = $config->bandSlug ?? 'default-band';
        $importBatchId = (string) Str::uuid();

        $setlistData = $this->setlistLoader->load($config);
        $resolved = $this->setlistLoader->resolveActiveSetlist($setlistData, $config->setlistId);

        $legacySetlistId = $resolved['id'];
        /** @var array<string, mixed> $setlist */
        $setlist = $resolved['setlist'];
        /** @var array<string, array<string, mixed>> $legacySongs */
        $legacySongs = $resolved['songs'];

        $show = new LegacyShowCandidate(
            legacySetlistId: $legacySetlistId,
            name: (string) ($setlist['name'] ?? 'Imported Show'),
        );

        $blockers = [];
        $warnings = [];
        $missingChartFiles = [];
        $missingSnippetFiles = [];
        $orphanSnippets = [];
        $zeroCueSongs = [];
        $placeholderChartsSkipped = [];
        $unresolvedRoles = [];

        /** @var list<string> $songOrder */
        $songOrder = $setlist['songOrder'] ?? [];

        if ($songOrder === []) {
            $blockers[] = 'Playlist contains zero songs.';
        }

        if (count($songOrder) > 999) {
            $blockers[] = 'Playlist exceeds maximum song_code range (999).';
        }

        $songs = [];
        $playlistItems = [];
        $cues = [];
        $legacyIdMappings = ['songs' => [], 'cues' => []];

        foreach ($songOrder as $index => $legacySongId) {
            $legacySongId = (string) $legacySongId;
            $position = $index + 1;
            $songCode = str_pad((string) $position, 3, '0', STR_PAD_LEFT);

            if (! isset($legacySongs[$legacySongId])) {
                $blockers[] = "Playlist references missing song: {$legacySongId}";

                continue;
            }

            /** @var array<string, mixed> $legacySong */
            $legacySong = $legacySongs[$legacySongId];

            $songs[] = new LegacySongCandidate(
                legacySongId: $legacySongId,
                songCode: $songCode,
                title: (string) ($legacySong['title'] ?? "Song {$legacySongId}"),
                artist: isset($legacySong['artist']) ? (string) $legacySong['artist'] : null,
                bpm: isset($legacySong['bpm']) ? (int) $legacySong['bpm'] : null,
                playlistPosition: $position,
                abletonPgm: $position,
            );

            $playlistItems[] = new LegacyPlaylistItemCandidate(
                legacySongId: $legacySongId,
                songCode: $songCode,
                position: $position,
                abletonPgm: $position,
            );

            $legacyIdMappings['songs'][$legacySongId] = [
                'song_code' => $songCode,
                'playlist_position' => $position,
            ];

            $cues[] = new LegacyCueCandidate(
                legacySongId: $legacySongId,
                songCode: $songCode,
                cueNumber: '000',
                sequenceOrder: 0,
                name: 'Preparation',
                legacyCueIndex: null,
                syntheticPreparation: true,
            );

            $legacyIdMappings['cues']["{$legacySongId}:000"] = [
                'legacy_cue_index' => null,
                'cue_number' => '000',
                'sequence_order' => 0,
                'cc16_equivalent' => 0,
            ];

            /** @var list<array<string, mixed>> $legacyCues */
            $legacyCues = $legacySong['cues'] ?? [];

            if ($legacyCues === []) {
                $zeroCueSongs[] = $legacySongId;
                $warnings[] = "Song {$legacySongId} has zero legacy cues (Cue 000 only).";
            }

            foreach ($legacyCues as $cueIndex => $legacyCue) {
                $cueNumber = str_pad((string) ($cueIndex + 1), 3, '0', STR_PAD_LEFT);

                $cues[] = new LegacyCueCandidate(
                    legacySongId: $legacySongId,
                    songCode: $songCode,
                    cueNumber: $cueNumber,
                    sequenceOrder: $cueIndex + 1,
                    name: (string) ($legacyCue['name'] ?? "Cue {$cueNumber}"),
                    legacyCueIndex: $cueIndex,
                    syntheticPreparation: false,
                    legacyBars: isset($legacyCue['bars']) ? (int) $legacyCue['bars'] : null,
                );

                $legacyIdMappings['cues']["{$legacySongId}:{$cueIndex}"] = [
                    'legacy_cue_index' => $cueIndex,
                    'cue_number' => $cueNumber,
                    'sequence_order' => $cueIndex + 1,
                    'cc16_equivalent' => $cueIndex + 1,
                ];
            }
        }

        $roleSlugsBySong = $this->collectRoleSlugsBySong($legacySongs, $config);
        $instrumentParts = $this->buildInstrumentPartCatalog($roleSlugsBySong);
        $songInstrumentParts = $this->buildSongInstrumentPartCandidates($roleSlugsBySong, $songs);

        [$charts, $chartIssues] = $this->buildChartCandidates(
            $legacySongs,
            $songs,
            $roleSlugsBySong,
            $pathResolver,
            $bandSlug,
        );

        $missingChartFiles = array_merge($missingChartFiles, $chartIssues['missing']);
        $placeholderChartsSkipped = array_merge($placeholderChartsSkipped, $chartIssues['placeholders']);

        [$snippets, $snippetIssues] = $this->buildSnippetCandidates(
            $legacySongs,
            $songs,
            $charts,
            $pathResolver,
            $bandSlug,
        );

        $missingSnippetFiles = array_merge($missingSnippetFiles, $snippetIssues['missing']);
        $orphanSnippets = array_merge($orphanSnippets, $snippetIssues['orphans']);
        $warnings = array_merge($warnings, $snippetIssues['warnings']);

        $songInstrumentParts = $this->linkChartKeysToSongInstrumentParts($songInstrumentParts, $charts);

        $musicians = $this->loadMusicianCandidates($config);

        $issues = new LegacyMigrationIssues(
            missingChartFiles: $missingChartFiles,
            missingSnippetFiles: $missingSnippetFiles,
            unresolvedRoles: $unresolvedRoles,
            orphanSnippets: $orphanSnippets,
            zeroCueSongs: $zeroCueSongs,
            placeholderChartsSkipped: $placeholderChartsSkipped,
            warnings: $warnings,
            blockers: $blockers,
        );

        return new LegacyMigrationPlan(
            importBatchId: $importBatchId,
            show: $show,
            songs: $songs,
            playlistItems: $playlistItems,
            cues: $cues,
            instrumentParts: $instrumentParts,
            songInstrumentParts: $songInstrumentParts,
            charts: $charts,
            snippets: $snippets,
            musicians: $musicians,
            issues: $issues,
            legacyIdMappings: $legacyIdMappings,
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $legacySongs
     * @return array<string, list<string>>
     */
    private function collectRoleSlugsBySong(array $legacySongs, LegacyImportConfig $config): array
    {
        $rolesBySong = [];

        foreach ($legacySongs as $legacySongId => $legacySong) {
            $rolesBySong[$legacySongId] = [];

            foreach ($legacySong['cues'] ?? [] as $legacyCue) {
                foreach (array_keys($legacyCue['visualSnippets'] ?? []) as $roleSlug) {
                    $rolesBySong[$legacySongId][$this->roleNormalizer->slug((string) $roleSlug)] = true;
                }
            }

            $songChartDir = $config->chartsDir().DIRECTORY_SEPARATOR.$legacySongId;
            if (is_dir($songChartDir)) {
                foreach (scandir($songChartDir) ?: [] as $file) {
                    if ($file === '.' || $file === '..' || str_starts_with($file, '.')) {
                        continue;
                    }

                    if (preg_match('/^(.+)\.(pdf|PDF)$/', $file, $matches)) {
                        $rolesBySong[$legacySongId][$matches[1]] = true;
                    }
                }
            }
        }

        return array_map(
            fn (array $roles) => array_keys($roles),
            $rolesBySong,
        );
    }

    /**
     * @param  array<string, list<string>>  $roleSlugsBySong
     * @return list<LegacyInstrumentPartCandidate>
     */
    private function buildInstrumentPartCatalog(array $roleSlugsBySong): array
    {
        $catalog = [];

        foreach ($roleSlugsBySong as $roleSlugs) {
            foreach ($roleSlugs as $roleSlug) {
                $key = $this->roleNormalizer->catalogKey($roleSlug);

                if (isset($catalog[$key])) {
                    continue;
                }

                $catalog[$key] = new LegacyInstrumentPartCandidate(
                    legacyRoleSlug: $roleSlug,
                    normalizedName: $this->roleNormalizer->normalize($roleSlug),
                    catalogKey: $key,
                );
            }
        }

        return array_values($catalog);
    }

    /**
     * @param  array<string, list<string>>  $roleSlugsBySong
     * @param  list<LegacySongCandidate>  $songs
     * @return list<LegacySongInstrumentPartCandidate>
     */
    private function buildSongInstrumentPartCandidates(array $roleSlugsBySong, array $songs): array
    {
        $songCodeByLegacyId = [];

        foreach ($songs as $song) {
            $songCodeByLegacyId[$song->legacySongId] = $song->songCode;
        }

        $candidates = [];

        foreach ($roleSlugsBySong as $legacySongId => $roleSlugs) {
            $songCode = $songCodeByLegacyId[$legacySongId] ?? null;

            if ($songCode === null) {
                continue;
            }

            foreach ($roleSlugs as $roleSlug) {
                $candidates[] = new LegacySongInstrumentPartCandidate(
                    legacySongId: $legacySongId,
                    songCode: $songCode,
                    legacyRoleSlug: $roleSlug,
                    normalizedInstrumentPartName: $this->roleNormalizer->normalize($roleSlug),
                    chartCandidateKey: null,
                    candidateKey: "{$legacySongId}:{$roleSlug}",
                );
            }
        }

        return $candidates;
    }

    /**
     * @param  list<LegacySongInstrumentPartCandidate>  $songInstrumentParts
     * @param  list<LegacyChartCandidate>  $charts
     * @return list<LegacySongInstrumentPartCandidate>
     */
    private function linkChartKeysToSongInstrumentParts(array $songInstrumentParts, array $charts): array
    {
        $chartKeyBySongRole = [];

        foreach ($charts as $chart) {
            foreach ($chart->assignedRoleSlugs as $roleSlug) {
                $chartKeyBySongRole["{$chart->legacySongId}:{$roleSlug}"] = $chart->candidateKey;
            }
        }

        return array_map(function (LegacySongInstrumentPartCandidate $sip) use ($chartKeyBySongRole) {
            $key = $chartKeyBySongRole["{$sip->legacySongId}:{$sip->legacyRoleSlug}"] ?? null;

            if ($key === null) {
                return $sip;
            }

            return new LegacySongInstrumentPartCandidate(
                legacySongId: $sip->legacySongId,
                songCode: $sip->songCode,
                legacyRoleSlug: $sip->legacyRoleSlug,
                normalizedInstrumentPartName: $sip->normalizedInstrumentPartName,
                chartCandidateKey: $key,
                candidateKey: $sip->candidateKey,
            );
        }, $songInstrumentParts);
    }

    /**
     * @param  array<string, array<string, mixed>>  $legacySongs
     * @param  list<LegacySongCandidate>  $songs
     * @param  array<string, list<string>>  $roleSlugsBySong
     * @return array{0: list<LegacyChartCandidate>, 1: array{missing: list<LegacyMissingAsset>, placeholders: list<string>}}
     */
    private function buildChartCandidates(
        array $legacySongs,
        array $songs,
        array $roleSlugsBySong,
        LegacyPathResolver $pathResolver,
        string $bandSlug,
    ): array {
        $songCodeByLegacyId = [];

        foreach ($songs as $song) {
            $songCodeByLegacyId[$song->legacySongId] = $song->songCode;
        }

        /** @var array<string, LegacyChartCandidate> $deduped */
        $deduped = [];
        $missing = [];
        $placeholders = [];

        foreach ($roleSlugsBySong as $legacySongId => $roleSlugs) {
            $songCode = $songCodeByLegacyId[$legacySongId] ?? null;

            if ($songCode === null) {
                continue;
            }

            foreach ($roleSlugs as $roleSlug) {
                $absolutePath = $pathResolver->discoverRoleChartPdf($legacySongId, $roleSlug);
                $source = 'filesystem';

                if ($absolutePath === null) {
                    $assignmentPath = $this->resolveChartFromAssignments(
                        $legacySongs[$legacySongId] ?? [],
                        $pathResolver,
                    );

                    if ($assignmentPath !== null) {
                        $absolutePath = $assignmentPath['path'];
                        $source = 'assignment';
                    }
                }

                if ($absolutePath === null) {
                    $missing[] = new LegacyMissingAsset(
                        assetType: 'chart',
                        legacySongId: $legacySongId,
                        legacyPath: "charts/{$legacySongId}/{$roleSlug}.pdf",
                        roleSlug: $roleSlug,
                    );

                    continue;
                }

                if ($pathResolver->isNoChartPlaceholder($absolutePath)) {
                    $placeholders[] = "{$legacySongId}:{$roleSlug}:{$absolutePath}";

                    continue;
                }

                $checksum = $pathResolver->fileChecksum($absolutePath);
                $dedupeKey = $checksum !== null
                    ? "{$legacySongId}:sha256:{$checksum}"
                    : "{$legacySongId}:path:".md5($absolutePath);

                if (isset($deduped[$dedupeKey])) {
                    $existing = $deduped[$dedupeKey];
                    $roles = $existing->assignedRoleSlugs;

                    if (! in_array($roleSlug, $roles, true)) {
                        $roles[] = $roleSlug;
                    }

                    $deduped[$dedupeKey] = new LegacyChartCandidate(
                        candidateKey: $existing->candidateKey,
                        legacySongId: $existing->legacySongId,
                        songCode: $existing->songCode,
                        legacyPath: $existing->legacyPath,
                        checksum: $existing->checksum,
                        title: $existing->title,
                        expectedStorageReference: $existing->expectedStorageReference,
                        assignedRoleSlugs: $roles,
                        source: $existing->source,
                        fileExists: $existing->fileExists,
                        skippedPlaceholder: $existing->skippedPlaceholder,
                    );

                    continue;
                }

                $title = basename($absolutePath, '.pdf') ?: "{$roleSlug} chart";

                $deduped[$dedupeKey] = new LegacyChartCandidate(
                    candidateKey: $dedupeKey,
                    legacySongId: $legacySongId,
                    songCode: $songCode,
                    legacyPath: $absolutePath,
                    checksum: $checksum,
                    title: $title,
                    expectedStorageReference: $pathResolver->expectedChartStorageReference($bandSlug, $songCode, $roleSlug),
                    assignedRoleSlugs: [$roleSlug],
                    source: $source,
                    fileExists: is_file($absolutePath),
                    skippedPlaceholder: false,
                );
            }
        }

        return [array_values($deduped), ['missing' => $missing, 'placeholders' => $placeholders]];
    }

    /**
     * @param  array<string, mixed>  $legacySong
     * @return array{path: string, originalname: string}|null
     */
    private function resolveChartFromAssignments(array $legacySong, LegacyPathResolver $pathResolver): ?array
    {
        $assignments = $legacySong['chartAssignments'] ?? [];
        $lastValid = null;

        foreach ($assignments as $assignment) {
            $filePath = $assignment['file']['path'] ?? null;

            if (! is_string($filePath) || $filePath === '') {
                continue;
            }

            if ($pathResolver->isNoChartPlaceholder($filePath)) {
                continue;
            }

            $absolute = $pathResolver->resolveProjectRelative($filePath);

            if (is_file($absolute)) {
                $lastValid = [
                    'path' => $absolute,
                    'originalname' => (string) ($assignment['file']['originalname'] ?? basename($absolute)),
                ];
            }
        }

        return $lastValid;
    }

    /**
     * @param  array<string, array<string, mixed>>  $legacySongs
     * @param  list<LegacySongCandidate>  $songs
     * @param  list<LegacyChartCandidate>  $charts
     * @return array{0: list<LegacySnippetCandidate>, 1: array{missing: list<LegacyMissingAsset>, orphans: list<string>, warnings: list<string>}}
     */
    private function buildSnippetCandidates(
        array $legacySongs,
        array $songs,
        array $charts,
        LegacyPathResolver $pathResolver,
        string $bandSlug,
    ): array {
        $songCodeByLegacyId = [];

        foreach ($songs as $song) {
            $songCodeByLegacyId[$song->legacySongId] = $song->songCode;
        }

        $chartKeyBySongRole = [];

        foreach ($charts as $chart) {
            foreach ($chart->assignedRoleSlugs as $roleSlug) {
                $chartKeyBySongRole["{$chart->legacySongId}:{$roleSlug}"] = $chart->candidateKey;
            }
        }

        $snippets = [];
        $missing = [];
        $orphans = [];
        $warnings = [];
        /** @var array<string, true> $activeSnippetKeys */
        $activeSnippetKeys = [];

        foreach ($legacySongs as $legacySongId => $legacySong) {
            $songCode = $songCodeByLegacyId[$legacySongId] ?? null;

            if ($songCode === null) {
                continue;
            }

            foreach ($legacySong['cues'] ?? [] as $cueIndex => $legacyCue) {
                $cueNumber = str_pad((string) ($cueIndex + 1), 3, '0', STR_PAD_LEFT);

                foreach ($legacyCue['visualSnippets'] ?? [] as $roleKey => $snippetData) {
                    $roleSlug = $this->roleNormalizer->slug((string) $roleKey);
                    $legacyPath = (string) ($snippetData['path'] ?? '');

                    if ($legacyPath === '') {
                        $orphans[] = "{$legacySongId}:{$cueIndex}:{$roleSlug}:missing_path";

                        continue;
                    }

                    $physicalPath = $pathResolver->snippetApiPathToPhysical(
                        str_starts_with($legacyPath, '/api/') ? $legacyPath : '/api/'.ltrim($legacyPath, '/'),
                    );

                    $activeKey = "{$legacySongId}:{$roleSlug}:{$cueNumber}";

                    if (isset($activeSnippetKeys[$activeKey])) {
                        $warnings[] = "Duplicate active snippet candidate for {$activeKey}; keeping first.";

                        continue;
                    }

                    $activeSnippetKeys[$activeKey] = true;
                    $fileExists = is_file($physicalPath);
                    $checksum = $fileExists ? $pathResolver->fileChecksum($physicalPath) : null;

                    if (! $fileExists) {
                        $missing[] = new LegacyMissingAsset(
                            assetType: 'snippet',
                            legacySongId: $legacySongId,
                            legacyPath: $legacyPath,
                            roleSlug: $roleSlug,
                            legacyCueIndex: $cueIndex,
                        );
                    }

                    $snippets[] = new LegacySnippetCandidate(
                        candidateKey: "{$legacySongId}:{$cueIndex}:{$roleSlug}",
                        legacySongId: $legacySongId,
                        songCode: $songCode,
                        legacyRoleSlug: $roleSlug,
                        normalizedInstrumentPartName: $this->roleNormalizer->normalize($roleSlug),
                        legacyCueIndex: $cueIndex,
                        cueNumber: $cueNumber,
                        sourceType: Snippet::SOURCE_CHART_CROP,
                        legacyPath: $legacyPath,
                        physicalPath: $fileExists ? $physicalPath : null,
                        checksum: $checksum,
                        expectedStorageReference: $pathResolver->expectedSnippetStorageReference(
                            $bandSlug,
                            $songCode,
                            $roleSlug,
                            $cueNumber,
                        ),
                        fileExists: $fileExists,
                        chartCandidateKey: $chartKeyBySongRole["{$legacySongId}:{$roleSlug}"] ?? null,
                        sourceMetadata: [
                            'legacy_song_id' => $legacySongId,
                            'legacy_cue_index' => $cueIndex,
                            'legacy_role_slug' => $roleSlug,
                            'legacy_path' => $legacyPath,
                            'legacy_timestamp' => $snippetData['timestamp'] ?? null,
                            'legacy_filename' => basename($legacyPath),
                            'crop' => [
                                'page' => null,
                                'x' => null,
                                'y' => null,
                                'width' => null,
                                'height' => null,
                            ],
                        ],
                    );
                }
            }
        }

        return [$snippets, ['missing' => $missing, 'orphans' => $orphans, 'warnings' => $warnings]];
    }

    /**
     * @return list<LegacyMusicianCandidate>
     */
    private function loadMusicianCandidates(LegacyImportConfig $config): array
    {
        $path = $config->musiciansPath();

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! isset($data['musicians']) || ! is_array($data['musicians'])) {
            return [];
        }

        $candidates = [];

        foreach ($data['musicians'] as $musician) {
            if (! is_array($musician)) {
                continue;
            }

            $candidates[] = new LegacyMusicianCandidate(
                legacyMusicianId: (string) ($musician['id'] ?? Str::uuid()),
                name: (string) ($musician['name'] ?? 'Unknown'),
                email: isset($musician['email']) ? (string) $musician['email'] : null,
                legacyRoleLabel: isset($musician['role']) ? (string) $musician['role'] : null,
            );
        }

        return $candidates;
    }
}
