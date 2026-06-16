<?php

namespace App\Services\Console;

/**
 * Builds operator-facing X32 audio routing workspace data from learned console summary.
 *
 * Source connectivity (PH043.01): routing assignment and live link status are separate.
 * Routing tables must never imply physical connection. Live AES50/Card status is only
 * Live AES50/Card status is read from /-stat/* during learn and on routing page load (live mode).
 */
class X32RoutingWorkspaceBuilder
{
    private const NOT_LEARNED = 'Not learned';

    private const UNASSIGNED = 'Unassigned';

    private const OUTPUT_NOT_RESOLVED = 'Main L/R output not resolved';

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
            'routing_state' => $this->computeRoutingLearnState($routing),
            'sources' => $sources,
            'console' => $this->buildFlowConsoleCard($sources, $routing),
            'destinations' => [
                $this->buildFlowFohCard($routing),
                $this->buildFlowIemCard($routing, $summary),
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
        $iems = $this->buildDetailIemOutputs($routing, $summary);
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
                    $this->buildDetailInputSourceCard(
                        key: 'stagebox_a',
                        title: 'Stagebox A',
                        viaLabel: 'AES50A',
                        sourceType: 'aes50_a',
                        routing: $routing,
                        defaultDeskRange: 'CH01–CH16',
                    ),
                    $this->buildDetailInputSourceCard(
                        key: 'stagebox_b',
                        title: 'Stagebox B',
                        viaLabel: 'AES50B',
                        sourceType: 'aes50_b',
                        routing: $routing,
                        defaultDeskRange: 'CH17–CH24',
                    ),
                    $this->buildDetailInputSourceCard(
                        key: 'ableton',
                        title: 'Ableton',
                        viaLabel: 'USB/Card',
                        sourceType: 'card_usb',
                        routing: $routing,
                        defaultDeskRange: 'CH25–CH32',
                    ),
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
        $routing = is_array($context['routing'] ?? null) ? $context['routing'] : [];

        return [
            'configuration_actions' => [
                'title' => 'Configuration Actions',
                'steps' => $this->buildBottomWorkflowSteps($context),
            ],
            'advanced' => [
                'title' => 'Advanced X32 Routing',
                'description' => 'Raw console routing tables for advanced users.',
                'categories' => $this->buildAdvancedCategoryChips($routing),
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
     * @param  array<string, mixed>  $routing
     * @return list<array<string, string>>
     */
    private function buildAdvancedCategoryChips(array $routing = []): array
    {
        $normalized = $this->normalized($routing);
        $hasInputBanks = ($normalized['input_banks'] ?? []) !== [];
        $hasOut = ($normalized['out_1_16'] ?? []) !== [];
        $hasCard = ($normalized['card_inputs'] ?? []) !== [];

        $chip = static fn (string $key, string $label, bool $available, string $pendingLabel = 'Pending', string $availableLabel = 'Available'): array => [
            'key' => $key,
            'label' => $label,
            'status_label' => $available ? $availableLabel : $pendingLabel,
            'state' => $available ? 'available' : 'pending',
        ];

        return [
            $chip('inputs', 'Inputs', $hasInputBanks, 'Pending', 'Inputs available'),
            $chip('out_1_16', 'Out 1-16', $hasOut, 'Pending', 'Out 1–16 available'),
            ['key' => 'user_out', 'label' => 'User Out', 'status_label' => 'User routing pending', 'state' => 'pending'],
            $chip('aes50a', 'AES50A', $this->hasInputBankSourceType($routing, 'aes50_a'), 'AES50 output learn pending'),
            $chip('aes50b', 'AES50B', $this->hasInputBankSourceType($routing, 'aes50_b'), 'AES50 output learn pending'),
            $chip('card_usb', 'Card / USB', $hasCard || $this->hasInputBankSourceType($routing, 'card_usb'), 'Card routing pending', 'Card routing available'),
            ['key' => 'p16_ultranet', 'label' => 'P16 / Ultranet', 'status_label' => 'Coming later', 'state' => 'pending'],
            ['key' => 'aux_out', 'label' => 'Aux Out', 'status_label' => 'Coming later', 'state' => 'pending'],
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
            $this->detailStatusTile('iems', 'IEM / Return Buses', $iems, 'monitor_buses'),
        ];
    }

    /**
     * @param  array<string, mixed>  $zone
     * @return array<string, mixed>
     */
    private function detailStatusTile(string $key, string $label, array $zone, string $kind): array
    {
        $operatorLabel = (string) ($zone['operator_label'] ?? '');
        $state = (string) ($zone['state'] ?? 'not_learned');

        if ($operatorLabel !== '') {
            $statusState = match ($state) {
                'routed', 'learned', 'buses_configured' => 'learned',
                'partial' => 'partial',
                'expected' => 'expected',
                'not_routed', 'not_configured' => 'not_learned',
                default => $state,
            };

            return [
                'key' => $key,
                'label' => $label,
                'status_label' => $operatorLabel,
                'status_state' => $statusState,
            ];
        }

        $statusLabel = match ($kind) {
            'stagebox' => match ($state) {
                'routed', 'learned', 'partial' => 'Routed',
                'expected' => 'Expected setup',
                'not_routed' => 'Not routed',
                default => 'Needs attention',
            },
            'ableton' => match ($state) {
                'routed', 'learned' => 'Routed',
                'expected' => 'Expected setup',
                'not_routed' => 'Not routed',
                default => 'Needs attention',
            },
            'output' => match ($state) {
                'learned' => 'Output resolved',
                'partial' => 'Partial routing',
                'not_learned' => 'Output not resolved',
                default => 'Needs attention',
            },
            'monitor_buses' => match ($state) {
                'buses_configured', 'learned' => (string) ($zone['operator_summary'] ?? 'Buses configured'),
                default => 'Not configured',
            },
            default => 'Needs attention',
        };

        $statusState = match ($statusLabel) {
            'Routed', 'Output resolved', 'Buses configured' => $state === 'partial' ? 'partial' : 'learned',
            'Expected setup' => 'expected',
            'Partial routing' => 'partial',
            default => in_array($state, ['routed', 'learned', 'buses_configured'], true) ? 'learned' : 'not_learned',
        };

        if (str_contains($statusLabel, 'bus')) {
            $statusState = $state === 'buses_configured' ? 'learned' : 'not_learned';
        }

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
     * Input Sources detail card — routing assignment in header pill; body shows link, range, result.
     *
     * @return array<string, mixed>
     */
    private function buildDetailInputSourceCard(
        string $key,
        string $title,
        string $viaLabel,
        string $sourceType,
        array $routing,
        string $defaultDeskRange,
    ): array {
        $hasNormalized = $this->normalized($routing) !== [];
        $isRouted = $this->hasInputBankSourceType($routing, $sourceType);
        $deskChannels = $this->learnedDeskChannels(null, $routing, $key) ?? $defaultDeskRange;
        $connectivity = $this->resolveSourceConnectivity($routing, $key);
        $result = $this->resolveSourceOperationalResult($key, $isRouted, $connectivity);

        return [
            'key' => $key,
            'title' => $title,
            'routing_pill' => $this->resolveDetailRoutingPill($isRouted, $hasNormalized, $viaLabel),
            'connectivity' => [
                'label' => (string) $connectivity['label'],
                'state' => (string) $connectivity['state'],
                'monitored' => (bool) ($connectivity['monitored'] ?? false),
            ],
            'channel_range' => $deskChannels,
            'result' => $result,
        ];
    }

    /**
     * @return array{label: string, state: string}
     */
    private function resolveDetailRoutingPill(bool $isRouted, bool $hasNormalized, string $viaLabel): array
    {
        if ($isRouted) {
            return [
                'label' => sprintf('Routed: %s', $viaLabel),
                'state' => 'routed',
            ];
        }

        if ($hasNormalized) {
            return [
                'label' => 'Not routed',
                'state' => 'not_routed',
            ];
        }

        return [
            'label' => 'Expected',
            'state' => 'expected',
        ];
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
        $learnedGroups = $this->channelAllocationGroupsFromInputBanks($routing);

        if ($learnedGroups !== []) {
            return $learnedGroups;
        }

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
    private function buildDetailIemOutputs(array $routing, array $summary = []): array
    {
        return $this->buildMonitorReturnBusSection($routing, $summary);
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
                        ),
                        'source' => (string) ($foh['left']['source'] ?? self::NOT_LEARNED),
                        'state' => (string) ($foh['left']['state'] ?? 'not_learned'),
                    ],
                    [
                        'label' => 'Main Right',
                        'route' => $this->formatDetailOutputRoute(
                            (string) ($foh['right']['output'] ?? self::NOT_LEARNED),
                        ),
                        'source' => (string) ($foh['right']['source'] ?? self::NOT_LEARNED),
                        'state' => (string) ($foh['right']['state'] ?? 'not_learned'),
                    ],
                ],
                'state' => (string) ($foh['state'] ?? 'not_learned'),
            ],
            'out_1_16' => $this->buildDetailOutBlocks($routing),
            'iems' => [
                'title' => (string) ($iems['title'] ?? 'IEM / Return Buses'),
                'summary' => (string) ($iems['summary'] ?? ''),
                'detail_line' => (string) ($iems['detail_line'] ?? ''),
                'columns' => is_array($iems['columns'] ?? null) ? $iems['columns'] : [],
                'mixes' => array_map(function (array $mix) {
                    return [
                        'number' => (int) ($mix['number'] ?? 0),
                        'name' => (string) ($mix['name'] ?? 'Return Bus'),
                        'bus' => (string) ($mix['bus'] ?? self::UNASSIGNED),
                        'output' => (string) ($mix['output'] ?? self::OUTPUT_NOT_RESOLVED),
                        'line' => (string) ($mix['line'] ?? ''),
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

    private function formatDetailOutputRoute(string $learnedOutput): string
    {
        if ($learnedOutput !== self::NOT_LEARNED && $learnedOutput !== self::UNASSIGNED) {
            return sprintf('→ %s', $learnedOutput);
        }

        return self::OUTPUT_NOT_RESOLVED;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildDetailOutBlocks(array $routing): array
    {
        $blocks = $this->learnedOutBanks($routing);

        if ($blocks === []) {
            return [
                'title' => 'Out 1–16',
                'summary' => self::NOT_LEARNED,
                'blocks' => [],
                'state' => 'not_learned',
            ];
        }

        $rows = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $rows[] = [
                'block' => (string) ($block['block'] ?? '—'),
                'output_range' => (string) ($block['output_range'] ?? '—'),
                'source_range' => (string) ($block['source_range'] ?? $block['raw_label'] ?? self::UNASSIGNED),
                'source_type' => (string) ($block['source_type'] ?? 'unknown'),
                'state' => 'learned',
            ];
        }

        return [
            'title' => 'Out 1–16',
            'summary' => sprintf('%d output blocks learned', count($rows)),
            'blocks' => $rows,
            'state' => 'learned',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildFlowConsoleCard(array $sources, array $routing): array
    {
        $coverage = $this->analyzeChannelRoutingCoverage($routing);
        $summaries = [];

        foreach ($sources as $source) {
            $routingAssignment = is_array($source['routing'] ?? null) ? $source['routing'] : [];
            $routingLabel = (string) ($routingAssignment['label'] ?? $source['routing_line'] ?? '—');
            $channelRange = (string) ($routingAssignment['line'] ?? '');

            if ($channelRange === '' || $channelRange === '—') {
                $channelRange = null;
            }

            $summaries[] = [
                'key' => (string) ($source['key'] ?? 'unknown'),
                'source' => (string) ($source['title'] ?? 'Source'),
                'routing_label' => $routingLabel,
                'channel_range' => $channelRange,
                'result_label' => (string) ($source['status_label'] ?? '—'),
                'status' => (string) ($source['status'] ?? 'not_routed'),
                'line' => sprintf(
                    '%s · %s',
                    $source['title'] ?? 'Source',
                    (string) ($routingAssignment['display_line'] ?? $source['routing_line'] ?? '—'),
                ),
            ];
        }

        $learnedRange = $this->consoleChannelRangeFromInputBanks($routing);

        return [
            'title' => 'Console Channels',
            'channel_range' => $learnedRange ?? 'CH01–CH32',
            'summaries' => $summaries,
            'status' => (string) $coverage['status'],
            'status_label' => (string) $coverage['status_label'],
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
                'learned' => 'Output resolved',
                'partial' => 'Partial routing',
                default => 'Output not resolved',
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
            return self::OUTPUT_NOT_RESOLVED;
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
    private function buildFlowIemCard(array $routing, array $summary = []): array
    {
        $section = $this->buildMonitorReturnBusSection($routing, $summary);
        $state = (string) ($section['state'] ?? 'not_configured');
        $columns = is_array($section['columns'] ?? null) ? $section['columns'] : [];

        if ($state === 'learned') {
            return [
                'key' => 'iems',
                'title' => 'IEM / Return Buses',
                'status' => 'learned',
                'status_label' => (string) ($section['operator_summary'] ?? sprintf('%d buses configured', count($section['mixes'] ?? []))),
                'summary' => (string) ($section['summary'] ?? ''),
                'detail_line' => (string) ($section['detail_line'] ?? ''),
                'columns' => $columns,
                'lines' => [],
            ];
        }

        if ($state === 'buses_configured') {
            return [
                'key' => 'iems',
                'title' => 'IEM / Return Buses',
                'status' => 'partial',
                'status_label' => (string) ($section['operator_summary'] ?? $section['summary'] ?? 'Buses configured'),
                'summary' => (string) ($section['detail_line'] ?? 'Output routing not resolved yet'),
                'detail_line' => (string) ($section['detail_line'] ?? 'Output routing not resolved yet'),
                'columns' => $columns,
                'lines' => [],
            ];
        }

        return [
            'key' => 'iems',
            'title' => 'IEM / Return Buses',
            'status' => 'not_learned',
            'status_label' => 'Not configured',
            'summary' => (string) ($section['detail_line'] ?? 'Return bus routing not available yet'),
            'detail_line' => (string) ($section['detail_line'] ?? 'Return bus routing not available yet'),
            'columns' => [],
            'lines' => [
                (string) ($section['detail_line'] ?? 'Return bus routing not available yet'),
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
        return $this->buildOperatorSourceRowCard(
            key: 'stagebox_a',
            title: 'Stagebox A',
            viaLabel: 'AES50A',
            sourceType: 'aes50_a',
            routing: $routing,
            connectionFallback: 'AES50A',
            defaultDeskRange: 'CH01–CH16',
            defaultCapacity: 16,
        );
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildSourceRowStageboxBCard(array $routing): array
    {
        $expectedRange = $this->abletonOccupiesUpperChannels($routing)
            ? 'CH17–CH24'
            : 'CH17–CH32';

        return $this->buildOperatorSourceRowCard(
            key: 'stagebox_b',
            title: 'Stagebox B',
            viaLabel: 'AES50B',
            sourceType: 'aes50_b',
            routing: $routing,
            connectionFallback: 'AES50B',
            defaultDeskRange: $expectedRange,
            defaultCapacity: 16,
        );
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildSourceRowAbletonCard(array $routing): array
    {
        return $this->buildOperatorSourceRowCard(
            key: 'ableton',
            title: 'Ableton',
            viaLabel: 'USB/Card',
            sourceType: 'card_usb',
            routing: $routing,
            connectionFallback: 'USB/Card',
            defaultDeskRange: 'CH25–CH32',
            defaultCapacity: 8,
            capacityNoun: 'returns',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOperatorSourceRowCard(
        string $key,
        string $title,
        string $viaLabel,
        string $sourceType,
        array $routing,
        string $connectionFallback,
        string $defaultDeskRange,
        int $defaultCapacity = 16,
        string $capacityNoun = 'inputs',
    ): array {
        $hasNormalized = $this->normalized($routing) !== [];
        $isRouted = $this->hasInputBankSourceType($routing, $sourceType);
        $deskChannels = $this->learnedDeskChannels(null, $routing, $key) ?? $defaultDeskRange;
        $inputCount = $this->inputCountForSourceType($routing, $sourceType) ?? $defaultCapacity;
        $connectivity = $this->resolveSourceConnectivity($routing, $key);
        $result = $this->resolveSourceOperationalResult($key, $isRouted, $connectivity);
        $routingAssignment = $this->resolveRoutingAssignmentPresentation(
            $isRouted,
            $hasNormalized,
            $viaLabel,
            $deskChannels,
            $defaultDeskRange,
        );

        return [
            'key' => $key,
            'title' => $title,
            'capacity' => sprintf('%d %s', $isRouted ? $inputCount : $defaultCapacity, $capacityNoun),
            'capacity_noun' => $capacityNoun,
            'status' => (string) $result['state'],
            'status_label' => (string) $result['label'],
            'result' => $result,
            'routing' => $routingAssignment,
            'connectivity' => $connectivity,
            'routing_prefix' => 'Assignment',
            'routing_line' => (string) $routingAssignment['display_line'],
            'connection' => (string) $connectivity['label'],
            'connection_fallback' => $connectionFallback,
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

        $sourceType = match ($key) {
            'stagebox_a' => 'aes50_a',
            'stagebox_b' => 'aes50_b',
            'ableton' => 'card_usb',
            default => null,
        };

        if ($sourceType !== null) {
            $ranges = $this->deskChannelRangesForSourceType($routing, $sourceType);

            if ($ranges !== []) {
                return implode(', ', $ranges);
            }
        }

        foreach ($routing['input_banks'] ?? [] as $bank) {
            if (! is_array($bank)) {
                continue;
            }

            $source = mb_strtolower((string) ($bank['source_type'] ?? ''));
            $channels = (string) ($bank['channels'] ?? $bank['desk_channels'] ?? $bank['console_channel_range'] ?? '');

            if ($key === 'stagebox_a' && str_contains($source, 'aes50') && str_contains($source, 'a') && $channels !== '') {
                return $this->normalizeDeskChannelRange($channels);
            }

            if ($key === 'stagebox_b' && str_contains($source, 'aes50') && str_contains($source, 'b') && $channels !== '') {
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
        $routingState = $this->computeRoutingLearnState($routing);
        $isPreview = ($context['workspace_mode'] ?? '') === 'preview';

        return [
            'learned_label' => (string) $routingState['label'],
            'learned_state' => (string) $routingState['state'],
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
            $routingLines = $this->routingLinesFromInputBanks($routing, $key, $label);
        }

        if ($routingLines === []) {
            $routingLines = $this->suggestedStageboxRoutingLines($label, $deskStart, $deskEnd, $key);
        }

        $normalizedConnection = $this->connectionLabelForSourceType($routing, match ($key) {
            'stagebox_a' => 'aes50_a',
            'stagebox_b' => 'aes50_b',
            default => null,
        });

        if ($normalizedConnection !== null) {
            $connection = $normalizedConnection;
        }

        $inputCount = $this->inputCountForSourceType($routing, match ($key) {
            'stagebox_a' => 'aes50_a',
            'stagebox_b' => 'aes50_b',
            default => null,
        }) ?? $inputCount;

        $sourceType = match ($key) {
            'stagebox_a' => 'aes50_a',
            'stagebox_b' => 'aes50_b',
            default => null,
        };
        $viaLabel = match ($key) {
            'stagebox_a' => 'AES50A',
            'stagebox_b' => 'AES50B',
            default => null,
        };
        $isRouted = $sourceType !== null && $this->hasInputBankSourceType($routing, $sourceType);
        $hasNormalized = $this->normalized($routing) !== [];

        if ($isRouted) {
            $state = 'routed';
            $operatorLabel = sprintf('Routed via %s', $viaLabel);
        } elseif ($hasNormalized) {
            $state = 'not_routed';
            $operatorLabel = 'Not routed';
        } else {
            $state = 'expected';
            $operatorLabel = 'Expected setup';
        }

        return [
            'key' => $key,
            'label' => $label,
            'connection' => $connection,
            'connection_state' => $isRouted ? 'learned' : 'not_learned',
            'input_count' => $inputCount,
            'input_count_label' => sprintf('%d inputs', $inputCount),
            'routing_lines' => $routingLines,
            'state' => $state,
            'operator_label' => $operatorLabel,
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

        foreach ($this->learnedInputBanks($routing) as $bank) {
            if (! is_array($bank)) {
                continue;
            }

            $source = (string) ($bank['source_type'] ?? '');

            if ($key === 'stagebox_a' && $source === 'aes50_a') {
                return true;
            }

            if ($key === 'stagebox_b' && $source === 'aes50_b') {
                return true;
            }
        }

        foreach ($routing['input_banks'] ?? [] as $bank) {
            if (! is_array($bank)) {
                continue;
            }

            $source = mb_strtolower((string) ($bank['source_type'] ?? ''));

            if ($key === 'stagebox_a' && str_contains($source, 'aes50') && str_contains($source, 'a')) {
                return true;
            }

            if ($key === 'stagebox_b' && str_contains($source, 'aes50') && str_contains($source, 'b')) {
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
        $hasNormalizedCard = $this->hasInputBankSourceType($routing, 'card_usb');
        $connection = $hasNormalizedCard
            ? ($this->connectionLabelForSourceType($routing, 'card_usb') ?? 'Card / USB')
            : ($learned !== null
                ? (string) ($learned['connection'] ?? 'Card / USB')
                : self::NOT_LEARNED);

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

        if ($returns === [] && $hasNormalizedCard) {
            foreach ($this->inputBanksForSourceType($routing, 'card_usb') as $bank) {
                $channels = $bank['console_channels'] ?? [];
                $range = (string) ($bank['source_range'] ?? $bank['raw_label'] ?? 'Card');

                foreach ($channels as $channelNumber) {
                    $returns[] = [
                        'return' => sprintf('Return %d', max(1, (int) $channelNumber - 24)),
                        'desk_channel' => sprintf('CH %02d', (int) $channelNumber),
                        'card_usb' => $range,
                        'state' => 'learned',
                    ];
                }
            }
        }

        if ($returns === []) {
            foreach ($this->templateCatalog->suggestedAbletonReturns() as $entry) {
                $returns[] = array_merge($entry, ['state' => 'suggested']);
            }
        }

        $isRouted = $hasNormalizedCard;
        $hasNormalized = $this->normalized($routing) !== [];

        if ($isRouted) {
            $state = 'routed';
            $operatorLabel = 'Routed via USB/Card';
        } elseif ($hasNormalized) {
            $state = 'not_routed';
            $operatorLabel = 'Not routed';
        } else {
            $state = 'expected';
            $operatorLabel = 'Expected setup';
        }

        return [
            'label' => 'Ableton',
            'connection' => $connection,
            'connection_state' => $isRouted ? 'learned' : 'not_learned',
            'expected_use' => 'Ableton returns usually use CH 25–32',
            'returns' => $returns,
            'state' => $state,
            'operator_label' => $operatorLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @param  array<int, array<string, mixed>>  $channels
     * @return list<array<string, mixed>>
     */
    private function buildChannelAllocation(array $routing, array $channels): array
    {
        $bankMap = $this->channelSourceMapFromInputBanks($routing);
        $learnedSources = is_array($routing['channel_sources'] ?? null)
            ? $routing['channel_sources']
            : [];

        $tiles = [];

        for ($number = 1; $number <= 32; $number++) {
            $channel = $channels[$number - 1] ?? [];
            $bankEntry = $bankMap[$number] ?? null;

            $learned = is_array($learnedSources[$number] ?? null)
                ? $learnedSources[$number]
                : (is_array($learnedSources[(string) $number] ?? null) ? $learnedSources[(string) $number] : null);

            if ($bankEntry !== null) {
                $group = (string) ($bankEntry['group'] ?? 'unknown');
                $sourceType = (string) ($bankEntry['source_label'] ?? self::NOT_LEARNED);
                $sourceSocket = (string) ($bankEntry['source_range'] ?? self::UNASSIGNED);
                $groupState = 'learned';
                $state = 'learned';
            } elseif ($learned !== null) {
                $sourceType = (string) ($learned['source_type'] ?? self::UNASSIGNED);
                $sourceSocket = (string) ($learned['source_socket'] ?? self::UNASSIGNED);
                $group = $this->operatorGroupKeyForLabel($sourceType) ?? $this->suggestedChannelGroupKey($number);
                $groupState = 'learned';
                $state = 'learned';
            } else {
                $sourceType = self::NOT_LEARNED;
                $sourceSocket = self::NOT_LEARNED;
                $group = $this->suggestedChannelGroupKey($number);
                $groupState = 'suggested';
                $state = 'not_learned';
            }

            $purpose = $learned !== null
                ? (string) ($learned['purpose'] ?? self::UNASSIGNED)
                : self::UNASSIGNED;

            $tiles[] = [
                'number' => $number,
                'label' => sprintf('CH %02d', $number),
                'name' => (string) ($channel['name'] ?? ''),
                'source_type' => $sourceType,
                'source_socket' => $sourceSocket,
                'purpose' => $purpose !== self::UNASSIGNED ? $purpose : self::UNASSIGNED,
                'group' => $group,
                'group_state' => $groupState,
                'state' => $state,
            ];
        }

        return $tiles;
    }

    private function suggestedChannelGroupKey(int $number): string
    {
        if ($number <= 16) {
            return 'stagebox_a';
        }

        if ($number <= 24) {
            return 'stagebox_b';
        }

        return 'ableton';
    }

    private function suggestedChannelGroup(int $number): string
    {
        return match ($this->suggestedChannelGroupKey($number)) {
            'stagebox_a' => 'Stagebox A',
            'stagebox_b' => 'Stagebox B',
            'ableton' => 'Ableton',
            default => 'Unassigned',
        };
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function buildFohOutput(array $routing): array
    {
        $mainLr = $this->learnedMainLr($routing);
        $state = is_array($mainLr) ? (string) ($mainLr['state'] ?? 'not_learned') : 'not_learned';

        if ($state === 'not_learned') {
            return [
                'label' => 'FOH',
                'left' => [
                    'label' => 'FOH Left',
                    'output' => self::NOT_LEARNED,
                    'source' => self::NOT_LEARNED,
                    'state' => 'not_learned',
                ],
                'right' => [
                    'label' => 'FOH Right',
                    'output' => self::NOT_LEARNED,
                    'source' => self::NOT_LEARNED,
                    'state' => 'not_learned',
                ],
                'state' => 'not_learned',
                'operator_label' => 'Output not resolved',
            ];
        }

        $left = is_array($mainLr['left'] ?? null) ? $mainLr['left'] : null;
        $right = is_array($mainLr['right'] ?? null) ? $mainLr['right'] : null;

        return [
            'label' => 'FOH',
            'left' => $this->formatMainLrSide('FOH Left', $left),
            'right' => $this->formatMainLrSide('FOH Right', $right),
            'state' => $state,
            'operator_label' => $state === 'learned' ? 'Output resolved' : 'Partial routing',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $side
     * @return array<string, mixed>
     */
    private function formatMainLrSide(string $label, ?array $side): array
    {
        if ($side === null) {
            return [
                'label' => $label,
                'output' => self::NOT_LEARNED,
                'source' => self::NOT_LEARNED,
                'state' => 'not_learned',
            ];
        }

        $outputNumber = (int) ($side['output_number'] ?? 0);
        $rawLabel = (string) ($side['raw_label'] ?? self::NOT_LEARNED);

        return [
            'label' => $label,
            'output' => $outputNumber > 0 ? sprintf('Out %d', $outputNumber) : self::NOT_LEARNED,
            'source' => $rawLabel !== '' ? $rawLabel : self::NOT_LEARNED,
            'state' => 'learned',
        ];
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

        foreach ($this->learnedInputBanks($routing) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $bank = (string) ($entry['bank'] ?? '');

            if ($bank !== '') {
                $learned[$bank] = $entry;
            }
        }

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
                'source_range' => $entry !== null
                    ? (string) ($entry['source_range'] ?? $entry['raw_label'] ?? self::UNASSIGNED)
                    : self::NOT_LEARNED,
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
        return $this->learnedInputBanks($routing) !== [];
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    private function hasLearnedOutputs(array $routing): bool
    {
        if (is_array($routing['xlr_outputs'] ?? null) && $routing['xlr_outputs'] !== []) {
            return true;
        }

        return $this->learnedOutBanks($routing) !== [];
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

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>
     */
    private function normalized(array $routing): array
    {
        return is_array($routing['normalized'] ?? null) ? $routing['normalized'] : [];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array<string, mixed>>
     */
    private function learnedInputBanks(array $routing): array
    {
        $banks = $this->normalized($routing)['input_banks'] ?? [];

        return is_array($banks) ? array_values(array_filter($banks, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array<string, mixed>>
     */
    private function learnedOutBanks(array $routing): array
    {
        $blocks = $this->normalized($routing)['out_1_16'] ?? [];

        return is_array($blocks) ? array_values(array_filter($blocks, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array<string, mixed>>
     */
    private function learnedCardInputs(array $routing): array
    {
        $entries = $this->normalized($routing)['card_inputs'] ?? [];

        return is_array($entries) ? array_values(array_filter($entries, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<string, mixed>|null
     */
    private function learnedMainLr(array $routing): ?array
    {
        $mainLr = $this->normalized($routing)['main_lr'] ?? null;

        return is_array($mainLr) ? $mainLr : null;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array{state: string, label: string}
     */
    private function computeRoutingLearnState(array $routing): array
    {
        $normalized = $this->normalized($routing);

        if ($normalized === []) {
            return ['state' => 'not_learned', 'label' => 'Awaiting console routing learn'];
        }

        $coverage = $this->analyzeChannelRoutingCoverage($routing);

        if (($coverage['status'] ?? '') === 'needs_attention') {
            return ['state' => 'partial', 'label' => 'Routing needs attention'];
        }

        $hasInputBanks = ($normalized['input_banks'] ?? []) !== [];
        $hasCardInputs = ($normalized['card_inputs'] ?? []) !== [];
        $hasOut = ($normalized['out_1_16'] ?? []) !== [];
        $mainLrState = (string) (($normalized['main_lr'] ?? [])['state'] ?? 'not_learned');
        $hasMainLr = $mainLrState === 'learned';
        $hasMainLrPartial = $mainLrState === 'partial';

        $learnedDomains = count(array_filter([
            $hasInputBanks,
            $hasCardInputs,
            $hasOut,
            $hasMainLr,
        ]));

        $anySignal = $hasInputBanks || $hasCardInputs || $hasOut || $hasMainLr || $hasMainLrPartial;

        if (! $anySignal) {
            return ['state' => 'not_learned', 'label' => 'Awaiting console routing learn'];
        }

        if (($coverage['status'] ?? '') === 'partial') {
            return ['state' => 'partial', 'label' => 'Partial routing'];
        }

        if ($hasMainLrPartial || ($learnedDomains > 0 && $learnedDomains < 4)) {
            return ['state' => 'partial', 'label' => 'Partial routing'];
        }

        if ($learnedDomains === 4 && ($coverage['status'] ?? '') === 'ok') {
            return ['state' => 'learned', 'label' => 'Routing from console'];
        }

        if (($coverage['status'] ?? '') === 'ok') {
            return ['state' => 'learned', 'label' => 'Routing from console'];
        }

        return ['state' => 'partial', 'label' => 'Partial routing'];
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    private function hasInputBankSourceType(array $routing, string $sourceType): bool
    {
        foreach ($this->learnedInputBanks($routing) as $bank) {
            if ((string) ($bank['source_type'] ?? '') === $sourceType) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array<string, mixed>>
     */
    private function inputBanksForSourceType(array $routing, string $sourceType): array
    {
        return array_values(array_filter(
            $this->learnedInputBanks($routing),
            fn (array $bank): bool => (string) ($bank['source_type'] ?? '') === $sourceType,
        ));
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<string>
     */
    private function deskChannelRangesForSourceType(array $routing, string $sourceType): array
    {
        $ranges = [];

        foreach ($this->inputBanksForSourceType($routing, $sourceType) as $bank) {
            $range = (string) ($bank['console_channel_range'] ?? '');

            if ($range !== '') {
                $ranges[] = $this->normalizeDeskChannelRange($range);
            }
        }

        return $ranges;
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    private function connectionLabelForSourceType(array $routing, ?string $sourceType): ?string
    {
        if ($sourceType === null || ! $this->hasInputBankSourceType($routing, $sourceType)) {
            return null;
        }

        $labels = array_map(
            fn (array $bank): string => (string) ($bank['raw_label'] ?? $bank['source_range'] ?? ''),
            $this->inputBanksForSourceType($routing, $sourceType),
        );
        $labels = array_values(array_filter($labels, fn (string $label): bool => $label !== ''));

        return match ($sourceType) {
            'aes50_a' => $labels !== [] ? 'AES50A · '.implode(', ', $labels) : 'AES50A',
            'aes50_b' => $labels !== [] ? 'AES50B · '.implode(', ', $labels) : 'AES50B',
            'card_usb' => $labels !== [] ? 'USB/Card · '.implode(', ', $labels) : 'USB/Card',
            default => $labels !== [] ? implode(', ', $labels) : null,
        };
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    private function inputCountForSourceType(array $routing, ?string $sourceType): ?int
    {
        if ($sourceType === null) {
            return null;
        }

        $count = 0;

        foreach ($this->inputBanksForSourceType($routing, $sourceType) as $bank) {
            $count += count($bank['console_channels'] ?? []);
        }

        return $count > 0 ? $count : null;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array<string, mixed>>
     */
    private function routingLinesFromInputBanks(array $routing, string $key, string $label): array
    {
        $sourceType = match ($key) {
            'stagebox_a' => 'aes50_a',
            'stagebox_b' => 'aes50_b',
            default => null,
        };

        if ($sourceType === null) {
            return [];
        }

        $lines = [];

        foreach ($this->inputBanksForSourceType($routing, $sourceType) as $bank) {
            $sourceRange = (string) ($bank['source_range'] ?? $bank['raw_label'] ?? '—');
            $deskRange = (string) ($bank['console_channel_range'] ?? '—');

            $lines[] = [
                'text' => sprintf('%s %s → %s', $label, $sourceRange, $this->normalizeDeskChannelRange($deskRange)),
                'state' => 'learned',
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array<int, array<string, mixed>>
     */
    private function channelSourceMapFromInputBanks(array $routing): array
    {
        $map = [];

        foreach ($this->learnedInputBanks($routing) as $bank) {
            $sourceType = (string) ($bank['source_type'] ?? 'unknown');
            $group = $this->operatorGroupKeyForSourceType($sourceType) ?? 'unknown';
            $sourceLabel = (string) ($bank['raw_label'] ?? $bank['source_range'] ?? self::NOT_LEARNED);
            $sourceRange = (string) ($bank['source_range'] ?? $bank['raw_label'] ?? '');

            foreach ($bank['console_channels'] ?? [] as $channelNumber) {
                $map[(int) $channelNumber] = [
                    'group' => $group,
                    'source_label' => $sourceLabel,
                    'source_range' => $sourceRange,
                    'source_type' => $sourceType,
                ];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array<string, mixed>>
     */
    private function channelAllocationGroupsFromInputBanks(array $routing): array
    {
        $groups = [];

        foreach ($this->learnedInputBanks($routing) as $bank) {
            $sourceType = (string) ($bank['source_type'] ?? 'unknown');
            $groupKey = $this->operatorGroupKeyForSourceType($sourceType);

            if ($groupKey === null) {
                continue;
            }

            $channels = $bank['console_channels'] ?? [];

            if ($channels === []) {
                continue;
            }

            $groups[] = [
                'key' => $groupKey,
                'label' => $this->operatorGroupLabelForKey($groupKey),
                'detail' => (string) ($bank['source_range'] ?? $bank['raw_label'] ?? '—'),
                'start' => min($channels),
                'end' => max($channels),
            ];
        }

        return $groups;
    }

    private function operatorGroupKeyForSourceType(string $sourceType): ?string
    {
        return match ($sourceType) {
            'aes50_a' => 'stagebox_a',
            'aes50_b' => 'stagebox_b',
            'card_usb' => 'ableton',
            'local' => 'local',
            default => null,
        };
    }

    private function operatorGroupLabelForKey(string $key): string
    {
        return match ($key) {
            'stagebox_a' => 'Stagebox A',
            'stagebox_b' => 'Stagebox B',
            'ableton' => 'Ableton',
            'local' => 'Local',
            default => 'Unknown',
        };
    }

    private function operatorGroupKeyForLabel(string $label): ?string
    {
        $normalized = mb_strtolower($label);

        if (str_contains($normalized, 'stagebox a') || str_contains($normalized, 'aes50a')) {
            return 'stagebox_a';
        }

        if (str_contains($normalized, 'stagebox b') || str_contains($normalized, 'aes50b')) {
            return 'stagebox_b';
        }

        if (str_contains($normalized, 'ableton') || str_contains($normalized, 'card')) {
            return 'ableton';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    private function consoleChannelRangeFromInputBanks(array $routing): ?string
    {
        $ranges = [];

        foreach ($this->learnedInputBanks($routing) as $bank) {
            $range = (string) ($bank['console_channel_range'] ?? '');

            if ($range !== '') {
                $ranges[] = $this->normalizeDeskChannelRange($range);
            }
        }

        return $ranges !== [] ? implode(', ', $ranges) : null;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return array{status: string, status_label: string, covered: int, total: int}
     */
    private function analyzeChannelRoutingCoverage(array $routing): array
    {
        $banks = $this->learnedInputBanks($routing);

        if ($banks === []) {
            return [
                'status' => 'not_learned',
                'status_label' => 'No routing learned',
                'covered' => 0,
                'total' => 32,
            ];
        }

        $channelAssignments = [];
        $needsAttention = false;
        $supportedSourceTypes = ['aes50_a', 'aes50_b', 'card_usb', 'local'];

        foreach ($banks as $bank) {
            $sourceType = (string) ($bank['source_type'] ?? '');

            if ($sourceType !== '' && ! in_array($sourceType, $supportedSourceTypes, true)) {
                $needsAttention = true;
            }

            foreach ($bank['console_channels'] ?? [] as $channelNumber) {
                $channelNumber = (int) $channelNumber;

                if ($channelNumber < 1 || $channelNumber > 32) {
                    $needsAttention = true;

                    continue;
                }

                if (isset($channelAssignments[$channelNumber])) {
                    $needsAttention = true;
                }

                $channelAssignments[$channelNumber] = true;
            }
        }

        $covered = count($channelAssignments);

        if ($needsAttention) {
            return [
                'status' => 'needs_attention',
                'status_label' => 'Routing needs attention',
                'covered' => $covered,
                'total' => 32,
            ];
        }

        if ($covered === 32) {
            return [
                'status' => 'ok',
                'status_label' => 'Channel routing OK',
                'covered' => $covered,
                'total' => 32,
            ];
        }

        if ($covered > 0) {
            return [
                'status' => 'partial',
                'status_label' => 'Partial routing',
                'covered' => $covered,
                'total' => 32,
            ];
        }

        return [
            'status' => 'needs_attention',
            'status_label' => 'Routing needs attention',
            'covered' => 0,
            'total' => 32,
        ];
    }

    /**
     * @param  array<string, mixed>  $routing
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function buildMonitorReturnBusSection(array $routing, array $summary): array
    {
        $buses = is_array($summary['buses'] ?? null) ? array_values(array_filter($summary['buses'], 'is_array')) : [];
        $busCount = count($buses);
        $iemOutputs = $this->buildIemOutputs($routing);
        $hasIemRouting = ($iemOutputs['state'] ?? '') === 'learned';

        if ($hasIemRouting) {
            $entries = $this->buildMonitorBusEntriesFromIemMixes($iemOutputs['mixes'] ?? []);
            $mixes = [];

            foreach ($entries as $entry) {
                $mix = $this->findIemMixForBusNumber($iemOutputs['mixes'] ?? [], (int) $entry['number']);

                $mixes[] = [
                    'number' => (int) $entry['number'],
                    'name' => (string) $entry['name'],
                    'bus' => (string) ($mix['bus'] ?? self::UNASSIGNED),
                    'output' => (string) ($mix['output'] ?? self::OUTPUT_NOT_RESOLVED),
                    'line' => (string) $entry['name'],
                    'state' => 'learned',
                ];
            }

            return [
                'title' => 'IEM / Return Buses',
                'summary' => sprintf('%d monitor mixes routed', count($mixes)),
                'detail_line' => '',
                'mixes' => $mixes,
                'columns' => $this->layoutMonitorBusColumns($entries),
                'state' => 'learned',
                'operator_summary' => sprintf('%d buses configured', count($mixes)),
                'operator_label' => sprintf('%d buses configured', count($mixes)),
            ];
        }

        if ($busCount > 0) {
            $entries = $this->buildMonitorBusEntriesFromSummaryBuses($buses);
            $mixes = array_map(fn (array $entry): array => [
                'number' => (int) $entry['number'],
                'name' => (string) $entry['name'],
                'bus' => self::UNASSIGNED,
                'output' => self::OUTPUT_NOT_RESOLVED,
                'line' => (string) $entry['name'],
                'state' => 'buses_configured',
            ], $entries);

            return [
                'title' => 'IEM / Return Buses',
                'summary' => sprintf('%d buses configured', min($busCount, 12)),
                'detail_line' => 'Output routing not resolved yet',
                'mixes' => $mixes,
                'columns' => $this->layoutMonitorBusColumns($entries),
                'state' => 'buses_configured',
                'operator_summary' => sprintf('%d buses configured', $busCount),
                'operator_label' => sprintf('%d buses configured', $busCount),
            ];
        }

        return [
            'title' => 'IEM / Return Buses',
            'summary' => 'Not configured',
            'detail_line' => 'Return bus routing not available yet',
            'mixes' => [[
                'number' => 0,
                'name' => 'Return buses',
                'bus' => self::UNASSIGNED,
                'output' => self::OUTPUT_NOT_RESOLVED,
                'line' => 'Return bus routing not available yet',
                'state' => 'not_learned',
            ]],
            'columns' => [],
            'state' => 'not_configured',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $buses
     * @return list<array{number: int, name: string, state: string}>
     */
    private function buildMonitorBusEntriesFromSummaryBuses(array $buses): array
    {
        $byNumber = [];

        foreach ($buses as $position => $bus) {
            if (! is_array($bus)) {
                continue;
            }

            $number = $this->resolveMonitorBusNumber($bus, $position + 1);

            if ($number < 1 || $number > 12) {
                continue;
            }

            $byNumber[$number] = [
                'number' => $number,
                'name' => $this->resolveMonitorBusDisplayName($bus, $number),
                'state' => 'buses_configured',
            ];
        }

        return $this->padMonitorBusEntries($byNumber);
    }

    /**
     * @param  list<array<string, mixed>>  $mixes
     * @return list<array{number: int, name: string, state: string}>
     */
    private function buildMonitorBusEntriesFromIemMixes(array $mixes): array
    {
        $byNumber = [];

        foreach ($mixes as $position => $mix) {
            if (! is_array($mix)) {
                continue;
            }

            $number = $this->resolveMonitorBusNumber($mix, $position + 1);
            $name = trim((string) ($mix['name'] ?? ''));

            if ($name === '') {
                $name = $this->monitorBusFallbackName($number);
            }

            $byNumber[$number] = [
                'number' => $number,
                'name' => $name,
                'state' => 'learned',
            ];
        }

        return $this->padMonitorBusEntries($byNumber);
    }

    /**
     * @param  array<int, array{number: int, name: string, state: string}>  $byNumber
     * @return list<array{number: int, name: string, state: string}>
     */
    private function padMonitorBusEntries(array $byNumber): array
    {
        $entries = [];

        for ($number = 1; $number <= 12; $number++) {
            $entries[] = $byNumber[$number] ?? [
                'number' => $number,
                'name' => $this->monitorBusFallbackName($number),
                'state' => 'not_routed',
            ];
        }

        return $entries;
    }

    /**
     * @param  list<array{number: int, name: string, state: string}>  $entries
     * @return list<list<array{number: int, name: string, state: string}>>
     */
    private function layoutMonitorBusColumns(array $entries): array
    {
        $columns = [[], [], []];

        foreach ($entries as $entry) {
            $columnIndex = intdiv(((int) $entry['number']) - 1, 4);

            if ($columnIndex >= 0 && $columnIndex < 3) {
                $columns[$columnIndex][] = $entry;
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $bus
     */
    private function resolveMonitorBusNumber(array $bus, int $fallbackIndex): int
    {
        foreach (['number', 'index'] as $key) {
            $value = (int) ($bus[$key] ?? 0);

            if ($value >= 1 && $value <= 12) {
                return $value;
            }
        }

        return min(12, max(1, $fallbackIndex));
    }

    /**
     * @param  array<string, mixed>  $bus
     */
    private function resolveMonitorBusDisplayName(array $bus, int $number): string
    {
        $name = trim((string) ($bus['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return $this->monitorBusFallbackName($number);
    }

    private function monitorBusFallbackName(int $number): string
    {
        return sprintf('Return %d', $number);
    }

    /**
     * @param  list<array<string, mixed>>  $mixes
     * @return array<string, mixed>|null
     */
    private function findIemMixForBusNumber(array $mixes, int $number): ?array
    {
        foreach ($mixes as $position => $mix) {
            if (! is_array($mix)) {
                continue;
            }

            if ($this->resolveMonitorBusNumber($mix, $position + 1) === $number) {
                return $mix;
            }
        }

        return null;
    }

    /**
     * Live link/connectivity for a routing source — never inferred from input banks.
     *
     * @param  array<string, mixed>  $routing
     * @return array{state: string, label: string, monitored: bool}
     */
    private function resolveSourceConnectivity(array $routing, string $sourceKey): array
    {
        $root = is_array($routing['source_connectivity'] ?? null)
            ? $routing['source_connectivity']
            : [];

        $entry = is_array($root[$sourceKey] ?? null) ? $root[$sourceKey] : null;

        if ($entry === null) {
            return [
                'state' => 'not_monitored',
                'label' => 'Status not monitored yet',
                'monitored' => false,
            ];
        }

        $state = mb_strtolower(trim((string) ($entry['state'] ?? 'unknown')));

        return [
            'state' => $state,
            'label' => match ($state) {
                'online' => 'Online',
                'offline' => 'Offline',
                'unknown' => 'Unknown',
                default => 'Status not monitored yet',
            },
            'monitored' => in_array($state, ['online', 'offline', 'unknown'], true),
        ];
    }

    /**
     * @param  array{state: string, label: string, monitored: bool}  $connectivity
     * @return array{state: string, label: string}
     */
    private function resolveSourceOperationalResult(string $sourceKey, bool $isRouted, array $connectivity): array
    {
        if (! $isRouted) {
            return [
                'state' => 'not_routed',
                'label' => 'Not routed',
            ];
        }

        return match ((string) ($connectivity['state'] ?? 'not_monitored')) {
            'online' => [
                'state' => 'ready',
                'label' => 'Ready',
            ],
            'offline' => [
                'state' => 'source_offline',
                'label' => $sourceKey === 'ableton'
                    ? 'Ableton/Card not available'
                    : 'Source offline',
            ],
            default => [
                'state' => 'disconnected',
                'label' => 'Disconnected',
            ],
        };
    }

    /**
     * @return array{state: string, label: string, line: string, display_line: string}
     */
    private function resolveRoutingAssignmentPresentation(
        bool $isRouted,
        bool $hasNormalized,
        string $viaLabel,
        string $deskChannels,
        string $defaultDeskRange,
    ): array {
        if ($isRouted) {
            $label = sprintf('Routed via %s', $viaLabel);

            return [
                'state' => 'routed',
                'label' => $label,
                'line' => $deskChannels,
                'display_line' => sprintf('%s · %s', $label, $deskChannels),
            ];
        }

        if ($hasNormalized) {
            return [
                'state' => 'not_routed',
                'label' => 'Not routed',
                'line' => '—',
                'display_line' => 'Not routed',
            ];
        }

        return [
            'state' => 'expected',
            'label' => 'Expected setup',
            'line' => $defaultDeskRange,
            'display_line' => sprintf('Expected setup · %s', $defaultDeskRange),
        ];
    }
}
