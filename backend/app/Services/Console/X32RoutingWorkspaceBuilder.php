<?php

namespace App\Services\Console;

/**
 * Builds operator-facing X32 audio routing workspace data from learned console summary.
 */
class X32RoutingWorkspaceBuilder
{
    private const NOT_LEARNED = 'Not learned';

    private const UNASSIGNED = 'Unassigned';

    public function __construct(
        private readonly X32RoutingTemplateCatalog $templateCatalog,
    ) {}

    /**
     * Complete routing flow row for PH041.02 — sources, console, destinations.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function buildRoutingFlowRow(array $summary): array
    {
        $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];

        $sources = [
            $this->buildSourceRowStageboxACard($routing),
            $this->buildSourceRowStageboxBCard($routing),
            $this->buildSourceRowAbletonCard($routing),
        ];

        return [
            'label' => 'Routing Flow',
            'sources' => $sources,
            'console' => $this->buildFlowConsoleCard($sources),
            'destinations' => [
                $this->buildFlowFohCard($routing),
                $this->buildFlowIemCard($routing),
            ],
        ];
    }

    /**
     * Configuration detail row for PH041.03 — production, input sources, outputs.
     *
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function buildConfigurationDetailRow(array $summary, array $context = []): array
    {
        $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];
        $channels = is_array($summary['channels'] ?? null) ? $summary['channels'] : [];

        $stageboxA = $this->buildStageboxSource('stagebox_a', 'Stagebox A', 'AES50A', $routing, 1, 16);
        $stageboxB = $this->buildStageboxSource('stagebox_b', 'Stagebox B', 'AES50B', $routing, 17, 32);
        $ableton = $this->buildAbletonSource($routing);
        $foh = $this->buildFohOutput($routing);
        $iems = $this->buildDetailIemOutputs($routing);
        $channelAllocation = $this->buildChannelAllocation($routing, $channels);
        $production = $this->buildProductionConfiguration(
            $routing,
            $stageboxA,
            $stageboxB,
            $ableton,
            $foh,
            $iems,
            $context,
        );

        $configuredType = is_string($routing['production_type'] ?? null)
            ? (string) $routing['production_type']
            : null;

        return [
            'production' => [
                'title' => 'Current Production Configuration',
                'name' => $production['name'],
                'learned_meta' => $this->buildLearnedMeta($routing, $summary, $context),
                'type' => [
                    'label' => $production['type'],
                    'state' => $production['type_state'],
                ],
                'status_grid' => $this->buildDetailStatusGrid($stageboxA, $stageboxB, $ableton, $foh, $iems),
                'future_configurations' => $this->buildFutureConfigurationTiles($configuredType),
                'actions' => $this->buildDetailActions(),
            ],
            'input_sources' => [
                'title' => 'Input Sources',
                'cards' => [
                    $this->buildDetailStageboxInputCard($stageboxA, 'stagebox_a'),
                    $this->buildDetailStageboxInputCard($stageboxB, 'stagebox_b'),
                    $this->buildDetailAbletonInputCard($ableton),
                ],
                'channel_allocation' => [
                    'title' => 'Channel Allocation Overview',
                    'groups' => $this->buildChannelAllocationGroups($routing, $stageboxA, $stageboxB, $ableton),
                    'channels' => $channelAllocation,
                    'legend' => [
                        ['key' => 'stagebox_a', 'label' => 'Stagebox A'],
                        ['key' => 'stagebox_b', 'label' => 'Stagebox B'],
                        ['key' => 'ableton', 'label' => 'Ableton Returns'],
                    ],
                ],
            ],
            'outputs' => $this->buildDetailOutputsCard($foh, $iems, $routing),
        ];
    }

    /**
     * Bottom row for PH041.04 — workflow actions and advanced routing entry.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function buildRoutingBottomRow(array $context = []): array
    {
        return [
            'configuration_actions' => [
                'title' => 'Configuration Actions',
                'steps' => $this->buildBottomWorkflowSteps($context),
            ],
            'advanced' => [
                'title' => 'Advanced X32 Routing',
                'description' => 'Raw console routing tables for advanced users.',
                'categories' => $this->buildAdvancedCategoryChips(),
                'action_label' => 'View Advanced Routing',
                'action_available' => false,
                'status_label' => 'Coming later',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function buildBottomWorkflowSteps(array $context): array
    {
        $learnUrl = is_string($context['learn_url'] ?? null) ? (string) $context['learn_url'] : null;

        $steps = [
            [
                'number' => 1,
                'id' => 'learn',
                'label' => 'Learn From Console',
                'description' => 'Read the current X32 routing and console configuration.',
                'state' => 'available',
                'status_label' => $learnUrl !== null ? 'Available' : 'Available from header',
                'url' => $learnUrl,
            ],
            [
                'number' => 2,
                'id' => 'edit',
                'label' => 'Edit Configuration',
                'description' => 'Change the software-side routing configuration.',
                'state' => 'not_available',
                'status_label' => 'Not available yet',
                'url' => null,
            ],
            [
                'number' => 3,
                'id' => 'preview',
                'label' => 'Preview Changes',
                'description' => 'Review what will change before writing to the console.',
                'state' => 'coming_later',
                'status_label' => 'Coming later',
                'url' => null,
            ],
            [
                'number' => 4,
                'id' => 'sync',
                'label' => 'Sync To Console',
                'description' => 'Push the prepared routing configuration to the X32.',
                'state' => 'coming_later',
                'status_label' => 'Coming later',
                'url' => null,
            ],
            [
                'number' => 5,
                'id' => 'save',
                'label' => 'Save Configuration',
                'description' => 'Save the current setup as Duo, LoFi Setup, Full Band Setup, 4 Piece Rock Band, or Custom.',
                'state' => 'coming_later',
                'status_label' => 'Coming later',
                'url' => null,
            ],
        ];

        return $steps;
    }

    /**
     * @return list<array<string, string>>
     */
    private function buildAdvancedCategoryChips(): array
    {
        return [
            ['key' => 'inputs', 'label' => 'Inputs'],
            ['key' => 'out_1_16', 'label' => 'Out 1-16'],
            ['key' => 'user_out', 'label' => 'User Out'],
            ['key' => 'aes50a', 'label' => 'AES50A'],
            ['key' => 'aes50b', 'label' => 'AES50B'],
            ['key' => 'card_usb', 'label' => 'Card / USB'],
            ['key' => 'p16_ultranet', 'label' => 'P16 / Ultranet'],
            ['key' => 'aux_out', 'label' => 'Aux Out'],
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $context
     * @return array<string, string>|null
     */
    private function buildLearnedMeta(array $routing, array $summary, array $context): ?array
    {
        if (($context['baseline_saved_at'] ?? null) === null) {
            return null;
        }

        $console = $context['device_name'] ?? $this->connectedConsoleLabel($routing, $summary) ?? 'console';
        $savedAt = $context['baseline_saved_at'];

        return [
            'primary' => sprintf('Learned from %s', $console),
            'secondary' => $savedAt instanceof \DateTimeInterface
                ? $savedAt->diffForHumans()
                : (string) $savedAt,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDetailStatusGrid(
        array $stageboxA,
        array $stageboxB,
        array $ableton,
        array $foh,
        array $iems,
    ): array {
        return [
            $this->detailStatusTile('stagebox_a', 'Stagebox A', $stageboxA, 'stagebox'),
            $this->detailStatusTile('stagebox_b', 'Stagebox B', $stageboxB, 'stagebox'),
            $this->detailStatusTile('ableton', 'Ableton', $ableton, 'ableton'),
            $this->detailStatusTile('foh', 'FOH', $foh, 'output'),
            $this->detailStatusTile('iems', 'IEMs', $iems, 'output'),
        ];
    }

    /**
     * @param  array<string, mixed>  $zone
     * @return array<string, mixed>
     */
    private function detailStatusTile(string $key, string $label, array $zone, string $kind): array
    {
        $state = (string) ($zone['state'] ?? 'not_learned');

        $statusLabel = match ($kind) {
            'stagebox' => match ($state) {
                'learned', 'partial' => 'Connected',
                default => 'Not Learned',
            },
            'ableton' => match ($state) {
                'learned' => 'Active',
                default => 'Not Learned',
            },
            'output' => match ($state) {
                'learned', 'partial' => 'Configured',
                default => 'Not Learned',
            },
            default => 'Not Learned',
        };

        $statusState = match ($statusLabel) {
            'Connected', 'Active', 'Configured' => $state === 'partial' ? 'partial' : 'learned',
            default => 'not_learned',
        };

        return [
            'key' => $key,
            'label' => $label,
            'status_label' => $statusLabel,
            'status_state' => $statusState,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFutureConfigurationTiles(?string $currentType): array
    {
        $tiles = [];

        foreach ($this->templateCatalog->productionConfigurations() as $configuration) {
            $tiles[] = [
                'id' => $configuration['id'],
                'name' => $configuration['name'],
                'description' => $configuration['description'],
                'is_current' => $currentType !== null && mb_strtolower($currentType) === mb_strtolower($configuration['name']),
                'future' => true,
            ];
        }

        return $tiles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDetailActions(): array
    {
        $actions = [];

        foreach ($this->buildActions() as $index => $action) {
            $actions[] = [
                'number' => $index + 1,
                'id' => $action['id'],
                'label' => $action['label'],
                'available' => (bool) ($action['available'] ?? false),
                'status_label' => ($action['available'] ?? false)
                    ? 'Available'
                    : ((string) ($action['future_label'] ?? 'Not available yet')),
            ];
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function buildDetailStageboxInputCard(array $source, string $key): array
    {
        $connection = (string) ($source['connection'] ?? self::NOT_LEARNED);
        $connectionType = $connection !== self::NOT_LEARNED
            ? $connection
            : ($key === 'stagebox_a' ? 'AES50A' : 'AES50B');

        return [
            'key' => $key,
            'title' => (string) ($source['label'] ?? 'Stagebox'),
            'connection_type' => $connectionType,
            'connection_status' => $this->detailInputConnectionStatus($source, 'stagebox'),
            'capacity' => (string) ($source['input_count_label'] ?? '16 inputs'),
            'secondary_note' => 'Assigned below',
        ];
    }

    /**
     * @param  array<string, mixed>  $ableton
     * @return array<string, mixed>
     */
    private function buildDetailAbletonInputCard(array $ableton): array
    {
        $connection = (string) ($ableton['connection'] ?? self::NOT_LEARNED);
        $returnCount = count($ableton['returns'] ?? []);

        return [
            'key' => 'ableton',
            'title' => 'Ableton',
            'connection_type' => $connection !== self::NOT_LEARNED ? $connection : 'USB/Card',
            'connection_status' => $this->detailInputConnectionStatus($ableton, 'ableton'),
            'capacity' => sprintf('%d returns', $returnCount > 0 ? $returnCount : 8),
            'secondary_note' => 'Returns assigned below',
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, string>
     */
    private function detailInputConnectionStatus(array $source, string $kind): array
    {
        $state = (string) ($source['state'] ?? 'not_learned');
        $connectionState = (string) ($source['connection_state'] ?? 'not_learned');

        if ($kind === 'ableton') {
            if ($state === 'learned') {
                return ['label' => 'Active', 'state' => 'learned'];
            }

            return ['label' => 'Expected', 'state' => 'suggested'];
        }

        if ($state === 'learned' || $state === 'partial' || $connectionState === 'learned') {
            return ['label' => 'Connected', 'state' => 'learned'];
        }

        return ['label' => 'Expected', 'state' => 'suggested'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildChannelAllocationGroups(
        array $routing,
        array $stageboxA,
        array $stageboxB,
        array $ableton,
    ): array {
        $stageboxAConnection = (string) ($stageboxA['connection'] ?? 'AES50A');
        $stageboxBConnection = (string) ($stageboxB['connection'] ?? 'AES50B');

        return [
            [
                'key' => 'stagebox_a',
                'label' => 'Stagebox A',
                'detail' => sprintf(
                    '%s 1–16',
                    $stageboxAConnection !== self::NOT_LEARNED ? $stageboxAConnection : 'AES50A',
                ),
                'start' => 1,
                'end' => 16,
            ],
            [
                'key' => 'stagebox_b',
                'label' => 'Stagebox B',
                'detail' => sprintf(
                    '%s 1–8',
                    $stageboxBConnection !== self::NOT_LEARNED ? $stageboxBConnection : 'AES50B',
                ),
                'start' => 17,
                'end' => 24,
            ],
            [
                'key' => 'ableton',
                'label' => 'Ableton',
                'detail' => 'Card 1–8',
                'start' => 25,
                'end' => 32,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildDetailIemOutputs(array $routing): array
    {
        $learned = $this->buildIemOutputs($routing);

        if (($learned['state'] ?? '') === 'learned') {
            return $learned;
        }

        $mixes = [];

        foreach ($this->templateCatalog->suggestedIemMixLabels() as $entry) {
            $mixes[] = array_merge($entry, ['state' => 'suggested']);
        }

        return [
            'label' => 'IEMs',
            'mixes' => $mixes,
            'state' => 'suggested',
        ];
    }

    /**
     * @param  array<string, mixed>  $foh
     * @param  array<string, mixed>  $iems
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildDetailOutputsCard(array $foh, array $iems, array $routing): array
    {
        $spare = $this->buildSpareOutputs($routing);
        $spareNumbers = [];

        foreach ($spare as $entry) {
            if (preg_match('/XLR Out (\d+)/', (string) ($entry['label'] ?? ''), $matches)) {
                $spareNumbers[] = (int) $matches[1];
            }
        }

        sort($spareNumbers);

        $spareSummary = $spareNumbers !== []
            ? sprintf('XLR %d–%d Available', min($spareNumbers), max($spareNumbers))
            : 'No spare outputs identified';

        return [
            'title' => 'Outputs',
            'foh' => [
                'title' => 'FOH',
                'lines' => [
                    [
                        'label' => 'Main Left',
                        'route' => $this->formatDetailOutputRoute(
                            (string) ($foh['left']['output'] ?? self::NOT_LEARNED),
                            'XLR 1',
                        ),
                        'source' => (string) ($foh['left']['source'] ?? self::NOT_LEARNED),
                        'state' => (string) ($foh['left']['state'] ?? 'not_learned'),
                    ],
                    [
                        'label' => 'Main Right',
                        'route' => $this->formatDetailOutputRoute(
                            (string) ($foh['right']['output'] ?? self::NOT_LEARNED),
                            'XLR 2',
                        ),
                        'source' => (string) ($foh['right']['source'] ?? self::NOT_LEARNED),
                        'state' => (string) ($foh['right']['state'] ?? 'not_learned'),
                    ],
                ],
                'state' => (string) ($foh['state'] ?? 'not_learned'),
            ],
            'iems' => [
                'title' => 'IEM Mixes',
                'mixes' => array_map(function (array $mix) {
                    return [
                        'name' => (string) ($mix['name'] ?? 'IEM Mix'),
                        'bus' => (string) ($mix['bus'] ?? self::UNASSIGNED),
                        'output' => (string) ($mix['output'] ?? self::NOT_LEARNED),
                        'line' => sprintf(
                            '%s → %s → %s',
                            $mix['name'] ?? 'IEM Mix',
                            $mix['bus'] ?? self::UNASSIGNED,
                            $mix['output'] ?? self::NOT_LEARNED,
                        ),
                        'state' => (string) ($mix['state'] ?? 'not_learned'),
                    ];
                }, $iems['mixes'] ?? []),
                'state' => (string) ($iems['state'] ?? 'not_learned'),
            ],
            'spare' => [
                'title' => 'Spare / Unassigned Outputs',
                'summary' => $spareSummary,
                'items' => $spare,
            ],
        ];
    }

    private function formatDetailOutputRoute(string $learnedOutput, string $suggestedOutput): string
    {
        if ($learnedOutput !== self::NOT_LEARNED && $learnedOutput !== self::UNASSIGNED) {
            return sprintf('→ %s', $learnedOutput);
        }

        return sprintf('→ %s (Suggested)', $suggestedOutput);
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @return array<string, mixed>
     */
    private function buildFlowConsoleCard(array $sources): array
    {
        $summaries = [];
        $hasLearned = false;
        $hasSuggested = false;

        foreach ($sources as $source) {
            $status = (string) ($source['status'] ?? 'not_learned');
            $hasLearned = $hasLearned || $status === 'learned';
            $hasSuggested = $hasSuggested || $status === 'suggested';

            $summaries[] = [
                'source' => (string) ($source['title'] ?? 'Source'),
                'channels' => (string) ($source['routing_line'] ?? self::NOT_LEARNED),
                'status' => $status,
                'line' => sprintf(
                    '%s → %s',
                    $source['title'] ?? 'Source',
                    $source['routing_line'] ?? self::NOT_LEARNED,
                ),
            ];
        }

        return [
            'title' => 'Console Channels',
            'channel_range' => 'CH01–CH32',
            'summaries' => $summaries,
            'status' => $hasLearned ? 'learned' : ($hasSuggested ? 'suggested' : 'not_learned'),
            'status_label' => $hasLearned ? 'Learned' : ($hasSuggested ? 'Suggested' : 'Not learned'),
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildFlowFohCard(array $routing): array
    {
        $foh = $this->buildFohOutput($routing);
        $state = (string) ($foh['state'] ?? 'not_learned');

        return [
            'key' => 'foh',
            'title' => 'FOH',
            'status' => $state === 'learned' || $state === 'partial' ? ($state === 'partial' ? 'partial' : 'learned') : 'not_learned',
            'status_label' => match ($state) {
                'learned' => 'Learned',
                'partial' => 'Partial',
                default => 'Not learned',
            },
            'lines' => [
                [
                    'label' => 'Main L',
                    'value' => $this->formatFohLine($foh['left']),
                ],
                [
                    'label' => 'Main R',
                    'value' => $this->formatFohLine($foh['right']),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $side
     */
    private function formatFohLine(array $side): string
    {
        $source = (string) ($side['source'] ?? self::NOT_LEARNED);
        $output = (string) ($side['output'] ?? self::NOT_LEARNED);

        if ($source === self::NOT_LEARNED && $output === self::NOT_LEARNED) {
            return self::NOT_LEARNED;
        }

        if ($output !== self::NOT_LEARNED && $source !== self::NOT_LEARNED) {
            return sprintf('%s → %s', $source, $output);
        }

        return $source !== self::NOT_LEARNED ? $source : $output;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildFlowIemCard(array $routing): array
    {
        $iems = $this->buildIemOutputs($routing);
        $state = (string) ($iems['state'] ?? 'not_learned');

        if ($state === 'learned') {
            $lines = [];

            foreach ($iems['mixes'] as $mix) {
                $lines[] = sprintf(
                    '%s → %s → %s',
                    $mix['name'],
                    $mix['bus'],
                    $mix['output'],
                );
            }

            return [
                'key' => 'iems',
                'title' => 'IEMs',
                'status' => 'learned',
                'status_label' => 'Learned',
                'summary' => sprintf('%d monitor mixes', count($lines)),
                'lines' => $lines,
            ];
        }

        return [
            'key' => 'iems',
            'title' => 'IEMs',
            'status' => 'suggested',
            'status_label' => 'Suggested',
            'summary' => 'Suggested monitor mixes',
            'lines' => [
                'IEM Mix 1 → Bus 1 → Not learned',
                'IEM Mix 2 → Bus 2 → Not learned',
                'IEM Mix 3 → Bus 3 → Not learned',
                'IEM Mix 4 → Bus 4 → Not learned',
            ],
        ];
    }

    /**
     * Source row cards for PH041.02 — where audio enters the console.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function buildSourceRow(array $summary): array
    {
        $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];

        return [
            'label' => 'Source Row',
            'cards' => [
                $this->buildSourceRowStageboxACard($routing),
                $this->buildSourceRowStageboxBCard($routing),
                $this->buildSourceRowAbletonCard($routing),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildSourceRowStageboxACard(array $routing): array
    {
        $learned = is_array($routing['stagebox_a'] ?? null) ? $routing['stagebox_a'] : null;
        $isLearned = $this->hasLearnedStageboxRouting($learned, $routing, 'stagebox_a');
        $deskChannels = $this->learnedDeskChannels($learned, $routing, 'stagebox_a') ?? 'CH01–CH16';

        return $this->sourceRowCard(
            key: 'stagebox_a',
            title: 'Stagebox A',
            connection: $this->learnedConnection($learned, 'AES50A'),
            capacity: sprintf('%d inputs', (int) ($learned['input_count'] ?? 16)),
            deskChannels: $deskChannels,
            isLearned: $isLearned,
            suggestedDeskChannels: 'CH01–CH16',
        );
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildSourceRowStageboxBCard(array $routing): array
    {
        $learned = is_array($routing['stagebox_b'] ?? null) ? $routing['stagebox_b'] : null;
        $isLearned = $this->hasLearnedStageboxRouting($learned, $routing, 'stagebox_b');
        $deskChannels = $this->learnedDeskChannels($learned, $routing, 'stagebox_b');

        $suggestedDeskChannels = $this->abletonOccupiesUpperChannels($routing)
            ? 'CH17–CH24'
            : 'CH17–CH32';

        if ($deskChannels === null) {
            $deskChannels = $suggestedDeskChannels;
        }

        return $this->sourceRowCard(
            key: 'stagebox_b',
            title: 'Stagebox B',
            connection: $this->learnedConnection($learned, 'AES50B'),
            capacity: sprintf('%d inputs', (int) ($learned['input_count'] ?? 16)),
            deskChannels: $deskChannels,
            isLearned: $isLearned,
            suggestedDeskChannels: $suggestedDeskChannels,
        );
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildSourceRowAbletonCard(array $routing): array
    {
        $learned = is_array($routing['ableton'] ?? null) ? $routing['ableton'] : null;
        $isLearned = $learned !== null && (
            is_array($learned['returns'] ?? null) && $learned['returns'] !== []
            || isset($learned['desk_channels'])
        );

        $deskChannels = is_string($learned['desk_channels'] ?? null)
            ? (string) $learned['desk_channels']
            : 'CH25–CH32';

        return $this->sourceRowCard(
            key: 'ableton',
            title: 'Ableton',
            connection: $learned !== null
                ? (string) ($learned['connection'] ?? 'USB/Card')
                : 'USB/Card',
            capacity: sprintf('%d returns', (int) ($learned['return_count'] ?? 8)),
            deskChannels: $deskChannels,
            isLearned: $isLearned,
            suggestedDeskChannels: 'CH25–CH32',
            capacityNoun: 'returns',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceRowCard(
        string $key,
        string $title,
        string $connection,
        string $capacity,
        string $deskChannels,
        bool $isLearned,
        string $suggestedDeskChannels,
        string $capacityNoun = 'inputs',
    ): array {
        if ($isLearned) {
            $status = 'learned';
            $statusLabel = 'Learned';
            $routingPrefix = 'Routed to';
            $routingLine = $deskChannels;
        } elseif ($suggestedDeskChannels !== '') {
            $status = 'suggested';
            $statusLabel = 'Suggested';
            $routingPrefix = 'Suggested';
            $routingLine = $suggestedDeskChannels;
        } else {
            $status = 'not_learned';
            $statusLabel = 'Not learned';
            $routingPrefix = 'Routing';
            $routingLine = self::NOT_LEARNED;
        }

        return [
            'key' => $key,
            'title' => $title,
            'connection' => $connection,
            'capacity' => $capacity,
            'capacity_noun' => $capacityNoun,
            'routing_prefix' => $routingPrefix,
            'routing_line' => $routingLine,
            'status' => $status,
            'status_label' => $statusLabel,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $learned
     */
    private function learnedConnection(?array $learned, string $fallback): string
    {
        if ($learned === null) {
            return $fallback;
        }

        $connection = trim((string) ($learned['connection'] ?? ''));

        if ($connection === '' || strtolower($connection) === strtolower(self::NOT_LEARNED)) {
            return $fallback;
        }

        return str_replace(['AES50 A', 'AES50 B', 'Card / USB', 'Card/USB'], ['AES50A', 'AES50B', 'USB/Card', 'USB/Card'], $connection);
    }

    /**
     * @param  array<string, mixed>|null  $learned
     * @param  array<string, mixed>  $routing
     */
    private function learnedDeskChannels(?array $learned, array $routing, string $key): ?string
    {
        if ($learned !== null && is_string($learned['desk_channels'] ?? null)) {
            return $this->normalizeDeskChannelRange((string) $learned['desk_channels']);
        }

        if ($learned !== null && is_array($learned['routing_lines'] ?? null)) {
            foreach ($learned['routing_lines'] as $line) {
                $text = is_array($line) ? (string) ($line['text'] ?? '') : (string) $line;

                if (preg_match('/CH\s*0?(\d+)\s*[–-]\s*0?(\d+)/i', $text, $matches)) {
                    return sprintf('CH%02d–CH%02d', (int) $matches[1], (int) $matches[2]);
                }
            }
        }

        foreach ($routing['input_banks'] ?? [] as $bank) {
            if (! is_array($bank)) {
                continue;
            }

            $source = mb_strtolower((string) ($bank['source_type'] ?? ''));
            $channels = (string) ($bank['channels'] ?? $bank['desk_channels'] ?? '');

            if ($key === 'stagebox_a' && str_contains($source, 'aes50a') && $channels !== '') {
                return $this->normalizeDeskChannelRange($channels);
            }

            if ($key === 'stagebox_b' && str_contains($source, 'aes50b') && $channels !== '') {
                return $this->normalizeDeskChannelRange($channels);
            }
        }

        return null;
    }

    private function normalizeDeskChannelRange(string $range): string
    {
        if (preg_match_all('/\d+/', $range, $matches) && count($matches[0]) >= 2) {
            return sprintf('CH%02d–CH%02d', (int) $matches[0][0], (int) $matches[0][1]);
        }

        return $range;
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    private function abletonOccupiesUpperChannels(array $routing): bool
    {
        if (is_array($routing['ableton'] ?? null)) {
            return true;
        }

        foreach ($routing['channel_sources'] ?? [] as $number => $source) {
            if (! is_array($source)) {
                continue;
            }

            $channel = is_int($number) ? $number : (int) $number;
            $type = mb_strtolower((string) ($source['source_type'] ?? ''));

            if ($channel >= 25 && str_contains($type, 'ableton')) {
                return true;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $summary  Learned console baseline/snapshot summary
     * @param  array<string, mixed>  $context  Optional workspace context (mode, baseline name)
     * @return array<string, mixed>
     */
    public function build(array $summary, array $context = []): array
    {
        $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];
        $channels = is_array($summary['channels'] ?? null) ? $summary['channels'] : [];

        $stageboxA = $this->buildStageboxSource('stagebox_a', 'Stagebox A', 'AES50 A', $routing, 1, 16);
        $stageboxB = $this->buildStageboxSource('stagebox_b', 'Stagebox B', 'AES50 B', $routing, 17, 32);
        $ableton = $this->buildAbletonSource($routing);
        $foh = $this->buildFohOutput($routing);
        $iems = $this->buildIemOutputs($routing);
        $channelAllocation = $this->buildChannelAllocation($routing, $channels);

        return [
            'header' => $this->buildHeader($routing, $summary, $context),
            'production_configuration' => $this->buildProductionConfiguration(
                $routing,
                $stageboxA,
                $stageboxB,
                $ableton,
                $foh,
                $iems,
                $context,
            ),
            'future_configurations' => $this->templateCatalog->productionConfigurations(),
            'input_sources' => [
                'stagebox_a' => $stageboxA,
                'stagebox_b' => $stageboxB,
                'ableton' => $ableton,
            ],
            'channel_allocation' => $channelAllocation,
            'outputs' => [
                'foh' => $foh,
                'iems' => $iems,
                'spare' => $this->buildSpareOutputs($routing),
            ],
            'actions' => $this->buildActions(),
            'advanced' => $this->buildAdvanced($routing),
            'input_banks' => $this->buildInputBanks($routing),
            'aes50' => $this->buildAes50($routing),
            'usb_card' => $this->buildUsbCard($routing),
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function buildHeader(array $routing, array $summary, array $context): array
    {
        $hasRouting = $routing !== [];
        $hasInputDetail = $this->hasLearnedInputBanks($routing)
            || is_array($routing['stagebox_a'] ?? null)
            || is_array($routing['channel_sources'] ?? null);

        $isPreview = ($context['workspace_mode'] ?? '') === 'preview';

        return [
            'learned_label' => $hasRouting && ($hasInputDetail || isset($routing['main_lr']))
                ? 'Learned from console'
                : ($hasRouting ? 'Partially learned' : 'Not learned yet'),
            'learned_state' => $hasRouting ? 'partial' : 'none',
            'sync_label' => $isPreview ? 'Unsynced changes' : 'In sync',
            'sync_state' => $isPreview ? 'unsynced' : 'in_sync',
            'connected_console' => $this->connectedConsoleLabel($routing, $summary),
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @param  array<string, mixed>  $summary
     */
    private function connectedConsoleLabel(array $routing, array $summary): ?string
    {
        if (isset($routing['device_key']) && is_string($routing['device_key'])) {
            return strtoupper(str_replace('-', ' ', $routing['device_key']));
        }

        if (isset($summary['device_name']) && is_string($summary['device_name'])) {
            return $summary['device_name'];
        }

        if (isset($routing['host'], $routing['port'])) {
            return sprintf('%s:%s', $routing['host'], $routing['port']);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductionConfiguration(
        array $routing,
        array $stageboxA,
        array $stageboxB,
        array $ableton,
        array $foh,
        array $iems,
        array $context,
    ): array {
        $configuredType = is_string($routing['production_type'] ?? null)
            ? (string) $routing['production_type']
            : null;

        $baselineName = is_string($context['baseline_name'] ?? null)
            ? (string) $context['baseline_name']
            : null;

        return [
            'name' => is_string($routing['configuration_name'] ?? null)
                ? (string) $routing['configuration_name']
                : ($baselineName ?? 'Current Console Configuration'),
            'type' => $configuredType ?? 'Unknown / Not learned',
            'type_state' => $configuredType !== null ? 'learned' : 'not_learned',
            'zones' => [
                'stagebox_a' => $this->zoneStatus($stageboxA),
                'stagebox_b' => $this->zoneStatus($stageboxB),
                'ableton' => $this->zoneStatus($ableton),
                'foh' => $this->zoneStatus($foh),
                'iems' => $this->zoneStatus($iems),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $zone
     * @return array<string, string>
     */
    private function zoneStatus(array $zone): array
    {
        $state = (string) ($zone['state'] ?? 'not_learned');

        return [
            'label' => $state === 'learned' || $state === 'partial'
                ? 'Configured'
                : 'Not learned',
            'state' => $state,
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildStageboxSource(
        string $key,
        string $label,
        string $defaultConnection,
        array $routing,
        int $deskStart,
        int $deskEnd,
    ): array {
        $learned = is_array($routing[$key] ?? null) ? $routing[$key] : null;
        $inputCount = (int) ($learned['input_count'] ?? 16);
        $connection = $learned !== null
            ? (string) ($learned['connection'] ?? $defaultConnection)
            : self::NOT_LEARNED;

        $routingLines = $this->learnedStageboxRoutingLines($learned, $label, $deskStart, $deskEnd);

        if ($routingLines === []) {
            $routingLines = $this->suggestedStageboxRoutingLines($label, $deskStart, $deskEnd, $key);
        }

        $state = $learned !== null ? 'learned' : 'not_learned';

        return [
            'key' => $key,
            'label' => $label,
            'connection' => $connection,
            'connection_state' => $learned !== null ? 'learned' : 'not_learned',
            'input_count' => $inputCount,
            'input_count_label' => sprintf('%d inputs', $inputCount),
            'routing_lines' => $routingLines,
            'state' => $this->hasLearnedStageboxRouting($learned, $routing, $key) ? 'learned' : 'not_learned',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $learned
     * @return list<array<string, mixed>>
     */
    private function learnedStageboxRoutingLines(?array $learned, string $label, int $deskStart, int $deskEnd): array
    {
        if ($learned === null) {
            return [];
        }

        if (is_array($learned['routing_lines'] ?? null) && $learned['routing_lines'] !== []) {
            return array_values(array_map(function ($line) {
                return [
                    'text' => (string) ($line['text'] ?? $line),
                    'state' => 'learned',
                ];
            }, $learned['routing_lines']));
        }

        if (isset($learned['desk_channels'], $learned['source_range'])) {
            return [[
                'text' => sprintf(
                    '%s %s → %s',
                    $label,
                    $learned['source_range'],
                    $learned['desk_channels'],
                ),
                'state' => 'learned',
            ]];
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suggestedStageboxRoutingLines(string $label, int $deskStart, int $deskEnd, string $key): array
    {
        if ($key === 'stagebox_a') {
            return [[
                'text' => sprintf('%s Inputs 1–16 → CH 01–16', $label),
                'state' => 'suggested',
            ]];
        }

        if ($key === 'stagebox_b') {
            return [
                [
                    'text' => sprintf('%s Inputs 1–8 → CH 17–24', $label),
                    'state' => 'suggested',
                ],
                [
                    'text' => sprintf('%s Inputs 9–16 → spare (when Ableton uses CH 25–32)', $label),
                    'state' => 'suggested',
                ],
            ];
        }

        return [[
            'text' => sprintf('%s → CH %02d–%02d', $label, $deskStart, $deskEnd),
            'state' => 'suggested',
        ]];
    }

    /**
     * @param  array<string, mixed>|null  $learned
     * @param  array<string, mixed>  $routing
     */
    private function hasLearnedStageboxRouting(?array $learned, array $routing, string $key): bool
    {
        if ($learned !== null && (
            isset($learned['routing_lines']) || isset($learned['desk_channels'])
        )) {
            return true;
        }

        foreach ($routing['input_banks'] ?? [] as $bank) {
            if (! is_array($bank)) {
                continue;
            }

            $source = mb_strtolower((string) ($bank['source_type'] ?? ''));

            if ($key === 'stagebox_a' && str_contains($source, 'aes50a')) {
                return true;
            }

            if ($key === 'stagebox_b' && str_contains($source, 'aes50b')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildAbletonSource(array $routing): array
    {
        $learned = is_array($routing['ableton'] ?? null) ? $routing['ableton'] : null;
        $connection = $learned !== null
            ? (string) ($learned['connection'] ?? 'Card / USB')
            : self::NOT_LEARNED;

        $returns = [];

        if ($learned !== null && is_array($learned['returns'] ?? null)) {
            foreach ($learned['returns'] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $returns[] = [
                    'return' => (string) ($entry['return'] ?? 'Return'),
                    'desk_channel' => (string) ($entry['desk_channel'] ?? '—'),
                    'card_usb' => (string) ($entry['card_usb'] ?? '—'),
                    'state' => 'learned',
                ];
            }
        }

        if ($returns === []) {
            foreach ($this->templateCatalog->suggestedAbletonReturns() as $entry) {
                $returns[] = array_merge($entry, ['state' => 'suggested']);
            }
        }

        return [
            'label' => 'Ableton',
            'connection' => $connection,
            'connection_state' => $learned !== null ? 'learned' : 'not_learned',
            'expected_use' => 'Ableton returns usually use CH 25–32',
            'returns' => $returns,
            'state' => $learned !== null ? 'learned' : 'not_learned',
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @param  array<int, array<string, mixed>>  $channels
     * @return list<array<string, mixed>>
     */
    private function buildChannelAllocation(array $routing, array $channels): array
    {
        $learnedSources = is_array($routing['channel_sources'] ?? null)
            ? $routing['channel_sources']
            : [];

        $tiles = [];

        for ($number = 1; $number <= 32; $number++) {
            $channel = $channels[$number - 1] ?? [];
            $learned = is_array($learnedSources[$number] ?? null)
                ? $learnedSources[$number]
                : (is_array($learnedSources[(string) $number] ?? null) ? $learnedSources[(string) $number] : null);

            $sourceType = $learned !== null
                ? (string) ($learned['source_type'] ?? self::UNASSIGNED)
                : self::NOT_LEARNED;

            $sourceSocket = $learned !== null
                ? (string) ($learned['source_socket'] ?? self::UNASSIGNED)
                : self::NOT_LEARNED;

            $purpose = $learned !== null
                ? (string) ($learned['purpose'] ?? self::UNASSIGNED)
                : self::UNASSIGNED;

            $group = $this->suggestedChannelGroup($number);
            $groupState = $learned !== null ? 'learned' : 'suggested';

            $tiles[] = [
                'number' => $number,
                'label' => sprintf('CH %02d', $number),
                'name' => (string) ($channel['name'] ?? ''),
                'source_type' => $sourceType,
                'source_socket' => $sourceSocket,
                'purpose' => $purpose !== self::UNASSIGNED ? $purpose : self::UNASSIGNED,
                'group' => $group,
                'group_state' => $groupState,
                'state' => $learned !== null ? 'learned' : 'not_learned',
            ];
        }

        return $tiles;
    }

    private function suggestedChannelGroup(int $number): string
    {
        if ($number <= 16) {
            return 'Stagebox A';
        }

        if ($number <= 24) {
            return 'Stagebox B';
        }

        return 'Ableton';
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildFohOutput(array $routing): array
    {
        $learned = is_array($routing['foh'] ?? null) ? $routing['foh'] : null;
        $mainLr = is_array($routing['main_lr'] ?? null) ? $routing['main_lr'] : null;

        $leftSource = $learned['left_source'] ?? ($mainLr['left'] ?? null);
        $rightSource = $learned['right_source'] ?? ($mainLr['right'] ?? null);

        $leftOutput = $learned['left_output'] ?? $this->findLearnedOutputAssignment($routing, 1);
        $rightOutput = $learned['right_output'] ?? $this->findLearnedOutputAssignment($routing, 2);

        $hasPartial = $leftSource !== null || $rightSource !== null;

        return [
            'label' => 'FOH',
            'left' => [
                'label' => 'FOH Left',
                'output' => $leftOutput ?? self::NOT_LEARNED,
                'source' => $leftSource !== null ? (string) $leftSource : self::NOT_LEARNED,
                'state' => ($leftOutput !== null || $leftSource !== null) ? 'partial' : 'not_learned',
            ],
            'right' => [
                'label' => 'FOH Right',
                'output' => $rightOutput ?? self::NOT_LEARNED,
                'source' => $rightSource !== null ? (string) $rightSource : self::NOT_LEARNED,
                'state' => ($rightOutput !== null || $rightSource !== null) ? 'partial' : 'not_learned',
            ],
            'state' => $learned !== null ? 'learned' : ($hasPartial ? 'partial' : 'not_learned'),
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    private function findLearnedOutputAssignment(array $routing, int $number): ?string
    {
        $entry = $this->findLearnedOutput($routing, 'xlr', $number);

        return $entry['assignment'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildIemOutputs(array $routing): array
    {
        $learned = is_array($routing['iem_mixes'] ?? null) ? $routing['iem_mixes'] : [];

        if ($learned !== []) {
            $mixes = [];

            foreach ($learned as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $mixes[] = [
                    'name' => (string) ($entry['name'] ?? 'IEM Mix'),
                    'bus' => (string) ($entry['bus'] ?? self::UNASSIGNED),
                    'output' => (string) ($entry['output'] ?? self::NOT_LEARNED),
                    'state' => 'learned',
                ];
            }

            return [
                'label' => 'IEMs',
                'mixes' => $mixes,
                'state' => 'learned',
            ];
        }

        $placeholderCount = 4;
        $mixes = [];

        for ($index = 1; $index <= $placeholderCount; $index++) {
            $mixes[] = [
                'name' => sprintf('IEM Mix %d', $index),
                'bus' => sprintf('Bus %d', $index),
                'output' => self::NOT_LEARNED,
                'state' => 'not_learned',
            ];
        }

        return [
            'label' => 'IEMs',
            'mixes' => $mixes,
            'state' => 'not_learned',
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array<string, mixed>>
     */
    private function buildSpareOutputs(array $routing): array
    {
        $assigned = [];
        $spare = [];

        for ($index = 1; $index <= 16; $index++) {
            $learned = $this->findLearnedOutput($routing, 'xlr', $index);

            if ($learned === null || ($learned['assignment'] ?? self::NOT_LEARNED) === self::NOT_LEARNED) {
                $spare[] = [
                    'label' => sprintf('XLR Out %d', $index),
                    'assignment' => self::UNASSIGNED,
                    'state' => 'unassigned',
                ];

                continue;
            }

            $assigned[] = $index;
        }

        if ($spare === [] && ! $this->hasLearnedOutputs($routing)) {
            return [[
                'label' => 'Physical outputs',
                'assignment' => self::NOT_LEARNED,
                'state' => 'not_learned',
            ]];
        }

        return $spare;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildActions(): array
    {
        return [
            [
                'id' => 'learn',
                'label' => 'Learn From Console',
                'description' => 'Read current console routing and store it as the current configuration.',
                'available' => true,
            ],
            [
                'id' => 'edit',
                'label' => 'Edit Configuration',
                'description' => 'Change software-side routing assignments.',
                'available' => false,
                'future_label' => 'Not available yet',
            ],
            [
                'id' => 'preview',
                'label' => 'Preview Changes',
                'description' => 'Review what will change before syncing to the desk.',
                'available' => false,
                'future_label' => 'Coming later',
            ],
            [
                'id' => 'sync',
                'label' => 'Sync To Console',
                'description' => 'Push routing configuration to the X32.',
                'available' => false,
                'future_label' => 'Coming later',
            ],
            [
                'id' => 'save',
                'label' => 'Save Configuration',
                'description' => 'Save current settings as a reusable production configuration.',
                'available' => false,
                'future_label' => 'Coming later',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array<string, mixed>>
     */
    private function buildInputBanks(array $routing): array
    {
        $defaults = [
            ['bank' => '1-8', 'label' => 'Inputs 1–8', 'channels' => 'CH 01–08'],
            ['bank' => '9-16', 'label' => 'Inputs 9–16', 'channels' => 'CH 09–16'],
            ['bank' => '17-24', 'label' => 'Inputs 17–24', 'channels' => 'CH 17–24'],
            ['bank' => '25-32', 'label' => 'Inputs 25–32', 'channels' => 'CH 25–32'],
        ];

        $learned = [];

        foreach ($routing['input_banks'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $bank = (string) ($entry['bank'] ?? '');

            if ($bank !== '') {
                $learned[$bank] = $entry;
            }
        }

        $banks = [];

        foreach ($defaults as $def) {
            $entry = $learned[$def['bank']] ?? null;

            $banks[] = [
                'label' => $def['label'],
                'channels' => $def['channels'],
                'source_type' => $entry !== null ? (string) ($entry['source_type'] ?? self::UNASSIGNED) : self::NOT_LEARNED,
                'source_range' => $entry !== null ? (string) ($entry['source_range'] ?? self::UNASSIGNED) : self::NOT_LEARNED,
                'state' => $entry !== null ? 'learned' : 'not_learned',
            ];
        }

        return $banks;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildAes50(array $routing): array
    {
        return [
            'aes50a' => $this->buildAes50Port('AES50 A', $routing['aes50a'] ?? null),
            'aes50b' => $this->buildAes50Port('AES50 B', $routing['aes50b'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $learned
     * @return array<string, mixed>
     */
    private function buildAes50Port(string $label, ?array $learned): array
    {
        if ($learned === null) {
            return [
                'label' => $label,
                'output_banks' => [
                    ['bank' => '1–8', 'source' => self::NOT_LEARNED, 'state' => 'not_learned'],
                    ['bank' => '9–16', 'source' => self::NOT_LEARNED, 'state' => 'not_learned'],
                ],
                'state' => 'not_learned',
            ];
        }

        $banks = [];

        foreach ($learned['output_banks'] ?? [] as $bank) {
            if (! is_array($bank)) {
                continue;
            }

            $banks[] = [
                'bank' => (string) ($bank['bank'] ?? '—'),
                'source' => (string) ($bank['source'] ?? self::UNASSIGNED),
                'state' => 'learned',
            ];
        }

        if ($banks === []) {
            $banks[] = [
                'bank' => '1–16',
                'source' => (string) ($learned['source'] ?? self::UNASSIGNED),
                'state' => 'learned',
            ];
        }

        return [
            'label' => $label,
            'output_banks' => $banks,
            'state' => 'learned',
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildUsbCard(array $routing): array
    {
        $usb = is_array($routing['usb_card'] ?? null) ? $routing['usb_card'] : null;

        return [
            'inputs' => $this->buildUsbCardRows($usb['inputs'] ?? null, 'input', 8),
            'outputs' => $this->buildUsbCardRows($usb['outputs'] ?? null, 'output', 8),
            'state' => $usb !== null ? 'learned' : 'not_learned',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $learned
     * @return list<array<string, mixed>>
     */
    private function buildUsbCardRows(?array $learned, string $direction, int $count): array
    {
        $rows = [];

        for ($index = 1; $index <= $count; $index++) {
            $entry = $learned[$index - 1] ?? null;
            $isLearned = is_array($entry);

            $rows[] = [
                'number' => $index,
                'label' => sprintf('Card / USB %s %d', ucfirst($direction), $index),
                'assignment' => $isLearned ? (string) ($entry['assignment'] ?? self::UNASSIGNED) : self::NOT_LEARNED,
                'state' => $isLearned ? 'learned' : 'not_learned',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array<string, mixed>>
     */
    private function buildAdvanced(array $routing): array
    {
        $categories = [
            'input_banks' => 'Input Banks',
            'out_1_16' => 'Out 1–16',
            'user_out' => 'User Out',
            'aes50a' => 'AES50 A',
            'aes50b' => 'AES50 B',
            'card' => 'Card / USB',
            'p16_ultranet' => 'P16 / Ultranet',
            'aux_out' => 'Aux Out',
        ];

        $learned = is_array($routing['advanced'] ?? null) ? $routing['advanced'] : [];
        $rows = [];

        foreach ($categories as $key => $label) {
            $entry = is_array($learned[$key] ?? null) ? $learned[$key] : null;

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'description' => $entry['description'] ?? 'X32 routing table category',
                'assignment' => $entry !== null
                    ? (string) ($entry['summary'] ?? 'Learned from console')
                    : self::NOT_LEARNED,
                'state' => $entry !== null ? 'learned' : 'not_learned',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    private function hasLearnedInputBanks(array $routing): bool
    {
        return is_array($routing['input_banks'] ?? null) && $routing['input_banks'] !== [];
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    private function hasLearnedOutputs(array $routing): bool
    {
        return is_array($routing['xlr_outputs'] ?? null) && $routing['xlr_outputs'] !== [];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, string>|null
     */
    private function findLearnedOutput(array $routing, string $type, int $number): ?array
    {
        foreach ($routing['xlr_outputs'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if ((int) ($entry['number'] ?? 0) === $number && ($entry['type'] ?? 'xlr') === $type) {
                return [
                    'assignment' => (string) ($entry['assignment'] ?? self::UNASSIGNED),
                    'purpose' => (string) ($entry['purpose'] ?? self::UNASSIGNED),
                ];
            }
        }

        return null;
    }
}
