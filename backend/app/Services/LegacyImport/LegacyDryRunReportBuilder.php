<?php

namespace App\Services\LegacyImport;

use App\DataTransferObjects\LegacyImport\LegacyDryRunValidationReport;
use App\DataTransferObjects\LegacyImport\LegacyDryRunValidationStatus;
use App\DataTransferObjects\LegacyImport\LegacyImportConfig;
use App\DataTransferObjects\LegacyImport\LegacyMigrationPlan;
use Illuminate\Support\Carbon;

class LegacyDryRunReportBuilder
{
    public function build(
        LegacyMigrationPlan $plan,
        LegacyImportConfig $config,
        int $setlistCount,
    ): LegacyDryRunValidationReport {
        $counts = $this->buildCounts($plan, $setlistCount);
        $assetFindings = $this->buildAssetFindings($plan);
        $mappingFindings = $this->buildMappingFindings($plan);
        $issues = $this->buildIssues($plan, $assetFindings, $mappingFindings);
        $status = $this->resolveStatus($plan, $issues);

        return new LegacyDryRunValidationReport(
            status: $status,
            importBatchId: $plan->importBatchId,
            legacySetlistId: $plan->show->legacySetlistId,
            showName: $plan->show->name,
            projectRoot: $config->projectRoot,
            counts: $counts,
            assetFindings: $assetFindings,
            mappingFindings: $mappingFindings,
            issues: $issues,
            generatedAt: Carbon::now()->toIso8601String(),
        );
    }

    /**
     * @return array<string, int>
     */
    private function buildCounts(LegacyMigrationPlan $plan, int $setlistCount): array
    {
        $syntheticCue000 = count(array_filter(
            $plan->cues,
            fn ($cue) => $cue->syntheticPreparation,
        ));

        return [
            'setlists' => $setlistCount,
            'songs' => count($plan->songs),
            'playlist_items' => count($plan->playlistItems),
            'cues' => count($plan->cues),
            'synthetic_cue_000' => $syntheticCue000,
            'instrument_part_candidates' => count($plan->instrumentParts),
            'song_instrument_part_candidates' => count($plan->songInstrumentParts),
            'chart_candidates' => count($plan->charts),
            'snippet_candidates' => count($plan->snippets),
            'musician_candidates' => count($plan->musicians),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAssetFindings(LegacyMigrationPlan $plan): array
    {
        $existingCharts = [];
        $missingCharts = [];
        $uploadFallbacks = [];
        $sharedCharts = [];
        $checksumDuplicates = [];

        foreach ($plan->charts as $chart) {
            $entry = [
                'candidate_key' => $chart->candidateKey,
                'legacy_song_id' => $chart->legacySongId,
                'song_code' => $chart->songCode,
                'legacy_path' => $chart->legacyPath,
                'checksum' => $chart->checksum,
                'source' => $chart->source,
                'assigned_role_slugs' => $chart->assignedRoleSlugs,
            ];

            if ($chart->fileExists) {
                $existingCharts[] = $entry;
            }

            if ($chart->source === 'assignment') {
                $uploadFallbacks[] = $entry;
            }

            if (count($chart->assignedRoleSlugs) >= 2) {
                $sharedCharts[] = $entry;
            }
        }

        foreach ($plan->issues->missingChartFiles as $missing) {
            $missingCharts[] = [
                'legacy_song_id' => $missing->legacySongId,
                'role_slug' => $missing->roleSlug,
                'legacy_path' => $missing->legacyPath,
            ];
        }

        $checksumGroups = [];

        foreach ($plan->charts as $chart) {
            if ($chart->checksum === null) {
                continue;
            }

            $groupKey = "{$chart->legacySongId}:{$chart->checksum}";
            $checksumGroups[$groupKey][] = $chart->candidateKey;
        }

        foreach ($checksumGroups as $key => $candidateKeys) {
            if (count($candidateKeys) > 1) {
                $checksumDuplicates[] = [
                    'group_key' => $key,
                    'candidate_keys' => $candidateKeys,
                ];
            }
        }

        $existingSnippets = [];
        $missingSnippets = [];

        foreach ($plan->snippets as $snippet) {
            $entry = [
                'candidate_key' => $snippet->candidateKey,
                'legacy_song_id' => $snippet->legacySongId,
                'song_code' => $snippet->songCode,
                'legacy_role_slug' => $snippet->legacyRoleSlug,
                'legacy_cue_index' => $snippet->legacyCueIndex,
                'cue_number' => $snippet->cueNumber,
                'legacy_path' => $snippet->legacyPath,
                'file_exists' => $snippet->fileExists,
            ];

            if ($snippet->fileExists) {
                $existingSnippets[] = $entry;
            } else {
                $missingSnippets[] = $entry;
            }
        }

        return [
            'existing_chart_files' => $existingCharts,
            'missing_chart_files' => $missingCharts,
            'existing_snippet_files' => $existingSnippets,
            'missing_snippet_files' => $missingSnippets,
            'nochart_txt_skipped' => $plan->issues->placeholderChartsSkipped,
            'upload_fallback_usage' => $uploadFallbacks,
            'shared_chart_candidates' => $sharedCharts,
            'checksum_duplicate_groups' => $checksumDuplicates,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMappingFindings(LegacyMigrationPlan $plan): array
    {
        $legacySongToSongCode = [];
        $legacyCueIndexToCueNumber = [];
        $cueNumberToSequenceOrder = [];
        $roleNormalization = [];
        $chartAssignmentToSharedChart = [];
        $snippetToSipCue = [];

        foreach ($plan->songs as $song) {
            $legacySongToSongCode[$song->legacySongId] = [
                'song_code' => $song->songCode,
                'title' => $song->title,
                'playlist_position' => $song->playlistPosition,
            ];
        }

        foreach ($plan->cues as $cue) {
            if ($cue->legacyCueIndex !== null) {
                $legacyCueIndexToCueNumber["{$cue->legacySongId}:{$cue->legacyCueIndex}"] = [
                    'cue_number' => $cue->cueNumber,
                    'name' => $cue->name,
                ];
            }

            $cueNumberToSequenceOrder["{$cue->legacySongId}:{$cue->cueNumber}"] = $cue->sequenceOrder;
        }

        foreach ($plan->instrumentParts as $part) {
            $roleNormalization[$part->legacyRoleSlug] = [
                'normalized_name' => $part->normalizedName,
                'catalog_key' => $part->catalogKey,
            ];
        }

        foreach ($plan->charts as $chart) {
            if (count($chart->assignedRoleSlugs) >= 2) {
                $chartAssignmentToSharedChart[$chart->candidateKey] = [
                    'legacy_song_id' => $chart->legacySongId,
                    'song_code' => $chart->songCode,
                    'assigned_role_slugs' => $chart->assignedRoleSlugs,
                    'checksum' => $chart->checksum,
                ];
            }
        }

        foreach ($plan->snippets as $snippet) {
            $snippetToSipCue[$snippet->candidateKey] = [
                'legacy_song_id' => $snippet->legacySongId,
                'song_code' => $snippet->songCode,
                'legacy_role_slug' => $snippet->legacyRoleSlug,
                'normalized_instrument_part' => $snippet->normalizedInstrumentPartName,
                'legacy_cue_index' => $snippet->legacyCueIndex,
                'cue_number' => $snippet->cueNumber,
                'source_type' => $snippet->sourceType,
                'chart_candidate_key' => $snippet->chartCandidateKey,
            ];
        }

        return [
            'legacy_song_id_to_song_code' => $legacySongToSongCode,
            'legacy_cue_index_to_cue_number' => $legacyCueIndexToCueNumber,
            'cue_number_to_sequence_order' => $cueNumberToSequenceOrder,
            'role_string_to_instrument_part' => $roleNormalization,
            'chart_assignment_to_shared_chart' => $chartAssignmentToSharedChart,
            'snippet_to_sip_cue' => $snippetToSipCue,
        ];
    }

    /**
     * @param  array<string, mixed>  $assetFindings
     * @param  array<string, mixed>  $mappingFindings
     * @return array<string, mixed>
     */
    private function buildIssues(
        LegacyMigrationPlan $plan,
        array $assetFindings,
        array $mappingFindings,
    ): array {
        $duplicateMappings = $this->detectDuplicateSnippetMappings($plan);
        $ambiguousChartAssignments = $this->detectAmbiguousChartAssignments($plan);
        $unresolvedRoles = $this->detectUnresolvedRoles($plan);

        return [
            'unresolved_roles' => array_map(fn ($r) => [
                'legacy_song_id' => $r['legacy_song_id'],
                'legacy_role_slug' => $r['legacy_role_slug'],
                'reason' => $r['reason'],
            ], $unresolvedRoles),
            'missing_files' => [
                'charts' => $assetFindings['missing_chart_files'],
                'snippets' => $assetFindings['missing_snippet_files'],
            ],
            'zero_cue_songs' => $plan->issues->zeroCueSongs,
            'orphan_snippets' => $plan->issues->orphanSnippets,
            'duplicate_mappings' => $duplicateMappings,
            'ambiguous_chart_assignments' => $ambiguousChartAssignments,
            'blockers' => $plan->issues->blockers,
            'warnings' => array_values(array_unique(array_merge(
                $plan->issues->warnings,
                $this->buildSupplementalWarnings($plan, $unresolvedRoles, $duplicateMappings),
            ))),
        ];
    }

    /**
     * @return list<array{legacy_song_id: string, legacy_role_slug: string, reason: string}>
     */
    private function detectUnresolvedRoles(LegacyMigrationPlan $plan): array
    {
        $unresolved = [];

        foreach ($plan->songInstrumentParts as $sip) {
            $hasSnippet = false;

            foreach ($plan->snippets as $snippet) {
                if ($snippet->legacySongId === $sip->legacySongId
                    && $snippet->legacyRoleSlug === $sip->legacyRoleSlug) {
                    $hasSnippet = true;

                    break;
                }
            }

            if ($hasSnippet && $sip->chartCandidateKey === null) {
                $unresolved[] = [
                    'legacy_song_id' => $sip->legacySongId,
                    'legacy_role_slug' => $sip->legacyRoleSlug,
                    'reason' => 'Snippet exists but no chart candidate resolved for role.',
                ];
            }
        }

        return array_merge(
            $unresolved,
            array_map(fn ($r) => [
                'legacy_song_id' => $r->legacySongId,
                'legacy_role_slug' => $r->legacyRoleSlug,
                'reason' => $r->reason,
            ], $plan->issues->unresolvedRoles),
        );
    }

    /**
     * @return list<string>
     */
    private function detectDuplicateSnippetMappings(LegacyMigrationPlan $plan): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($plan->snippets as $snippet) {
            $key = "{$snippet->legacySongId}:{$snippet->legacyRoleSlug}:{$snippet->cueNumber}";

            if (isset($seen[$key])) {
                $duplicates[] = "Duplicate snippet mapping for {$key}";

                continue;
            }

            $seen[$key] = true;
        }

        return $duplicates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectAmbiguousChartAssignments(LegacyMigrationPlan $plan): array
    {
        /** @var array<string, list<string>> $roleChartKeys */
        $roleChartKeys = [];

        foreach ($plan->charts as $chart) {
            foreach ($chart->assignedRoleSlugs as $roleSlug) {
                $roleKey = "{$chart->legacySongId}:{$roleSlug}";
                $roleChartKeys[$roleKey][] = $chart->candidateKey;
            }
        }

        $ambiguous = [];

        foreach ($roleChartKeys as $roleKey => $candidateKeys) {
            $unique = array_unique($candidateKeys);

            if (count($unique) > 1) {
                $ambiguous[] = [
                    'role_key' => $roleKey,
                    'chart_candidate_keys' => array_values($unique),
                    'reason' => 'Multiple distinct chart candidates mapped to the same role.',
                ];
            }
        }

        return $ambiguous;
    }

    /**
     * @param  list<array{legacy_song_id: string, legacy_role_slug: string, reason: string}>  $unresolvedRoles
     * @param  list<string>  $duplicateMappings
     * @return list<string>
     */
    private function buildSupplementalWarnings(
        LegacyMigrationPlan $plan,
        array $unresolvedRoles,
        array $duplicateMappings,
    ): array {
        $warnings = [];

        if ($plan->issues->missingChartFiles !== []) {
            $warnings[] = count($plan->issues->missingChartFiles).' chart file(s) missing on disk.';
        }

        if ($plan->issues->missingSnippetFiles !== []) {
            $warnings[] = count($plan->issues->missingSnippetFiles).' snippet file(s) missing on disk.';
        }

        if ($plan->issues->zeroCueSongs !== []) {
            $warnings[] = count($plan->issues->zeroCueSongs).' song(s) have zero legacy cues.';
        }

        if ($unresolvedRoles !== []) {
            $warnings[] = count($unresolvedRoles).' unresolved role(s) require review.';
        }

        if ($duplicateMappings !== []) {
            $warnings[] = count($duplicateMappings).' duplicate snippet mapping(s) detected.';
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $issues
     */
    private function resolveStatus(LegacyMigrationPlan $plan, array $issues): string
    {
        if ($plan->issues->blockers !== [] || $issues['blockers'] !== []) {
            return LegacyDryRunValidationStatus::BLOCKED;
        }

        $hasWarnings = $issues['warnings'] !== []
            || $issues['missing_files']['charts'] !== []
            || $issues['missing_files']['snippets'] !== []
            || $issues['zero_cue_songs'] !== []
            || $issues['orphan_snippets'] !== []
            || $issues['unresolved_roles'] !== []
            || $issues['duplicate_mappings'] !== []
            || $issues['ambiguous_chart_assignments'] !== [];

        if ($hasWarnings) {
            return LegacyDryRunValidationStatus::PASS_WITH_WARNINGS;
        }

        return LegacyDryRunValidationStatus::PASS;
    }
}
