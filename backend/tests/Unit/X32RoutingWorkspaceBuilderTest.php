<?php

namespace Tests\Unit;

use App\Services\Console\X32RoutingWorkspaceBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32RoutingWorkspaceBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_routing_bottom_row_with_workflow_steps_and_advanced_categories(): void
    {
        $bottom = app(X32RoutingWorkspaceBuilder::class)->buildRoutingBottomRow([
            'learn_url' => '/shows/1/console/learn',
            'routing' => $this->sampleNormalizedRouting()['routing'],
        ]);

        $this->assertSame('Configuration Actions', $bottom['configuration_actions']['title']);
        $this->assertCount(5, $bottom['configuration_actions']['steps']);
        $this->assertSame('Learn From Console', $bottom['configuration_actions']['steps'][0]['label']);
        $this->assertSame('Available', $bottom['configuration_actions']['steps'][0]['status_label']);
        $this->assertSame('Advanced X32 Routing', $bottom['advanced']['title']);
        $this->assertCount(8, $bottom['advanced']['categories']);
        $this->assertSame('Inputs available', $bottom['advanced']['categories'][0]['status_label']);
        $this->assertFalse($bottom['advanced']['action_available']);
    }

    #[Test]
    public function it_builds_configuration_detail_row_from_normalized_input_banks(): void
    {
        $detail = app(X32RoutingWorkspaceBuilder::class)->buildConfigurationDetailRow(
            $this->sampleNormalizedRouting(),
            [
                'baseline_name' => 'Scene Baseline',
                'baseline_saved_at' => now()->subMinutes(2),
                'device_name' => 'Main X32',
            ],
        );

        $this->assertSame('Current Production Configuration', $detail['production']['title']);
        $this->assertSame('Scene Baseline', $detail['production']['name']);
        $this->assertSame('Learned from Main X32 · Scene unknown', $detail['production']['learned_meta']['primary']);
        $this->assertSame('Routed via AES50A', $detail['production']['status_grid'][0]['status_label']);
        $this->assertSame('Routed via AES50B', $detail['production']['status_grid'][1]['status_label']);
        $this->assertSame('Routed via USB/Card', $detail['production']['status_grid'][2]['status_label']);
        $this->assertSame('Routed: AES50A', $detail['input_sources']['cards'][0]['routing_pill']['label']);
        $this->assertSame('Routed: AES50B', $detail['input_sources']['cards'][1]['routing_pill']['label']);
        $this->assertSame('Routed: USB/Card', $detail['input_sources']['cards'][2]['routing_pill']['label']);
        $this->assertSame('Status not monitored yet', $detail['input_sources']['cards'][0]['connectivity']['label']);
        $this->assertSame('Disconnected', $detail['input_sources']['cards'][0]['result']['label']);
        $this->assertCount(32, $detail['input_sources']['channel_allocation']['channels']);
        $this->assertSame('learned', $detail['input_sources']['channel_allocation']['channels'][0]['state']);
        $this->assertSame('stagebox_a', $detail['input_sources']['channel_allocation']['channels'][0]['group']);
    }

    #[Test]
    public function it_builds_learned_meta_with_operator_scene_number_from_summary(): void
    {
        $summary = $this->sampleNormalizedRouting();
        $summary['scene_number'] = '02';

        $detail = app(X32RoutingWorkspaceBuilder::class)->buildConfigurationDetailRow($summary, [
            'baseline_saved_at' => now(),
            'device_name' => 'Main X32',
        ]);

        $this->assertSame('Learned from Main X32 · Scene 02', $detail['production']['learned_meta']['primary']);
    }

    #[Test]
    public function it_builds_learned_meta_with_scene_name_when_available(): void
    {
        $summary = $this->sampleNormalizedRouting();
        $summary['scene_number'] = '02';
        $summary['scene_name'] = 'Worship Set A';

        $detail = app(X32RoutingWorkspaceBuilder::class)->buildConfigurationDetailRow($summary, [
            'baseline_saved_at' => now(),
            'device_name' => 'Main X32',
        ]);

        $this->assertSame(
            'Learned from Main X32 · Scene 02 – Worship Set A',
            $detail['production']['learned_meta']['primary'],
        );
    }

    #[Test]
    public function it_converts_zero_based_osc_scene_index_to_operator_scene_number(): void
    {
        $summary = $this->sampleNormalizedRouting();
        $summary['routing']['scene_osc_index'] = 1;

        $detail = app(X32RoutingWorkspaceBuilder::class)->buildConfigurationDetailRow($summary, [
            'baseline_saved_at' => now(),
            'device_name' => 'Main X32',
        ]);

        $this->assertSame('Learned from Main X32 · Scene 02', $detail['production']['learned_meta']['primary']);
        $this->assertStringNotContainsString('Scene 01', $detail['production']['learned_meta']['primary']);
    }

    #[Test]
    public function it_does_not_display_raw_zero_based_index_as_operator_scene_number(): void
    {
        $summary = $this->sampleNormalizedRouting();
        $summary['routing']['scene_index'] = 1;

        $detail = app(X32RoutingWorkspaceBuilder::class)->buildConfigurationDetailRow($summary, [
            'baseline_saved_at' => now(),
            'device_name' => 'Main X32',
        ]);

        $primary = $detail['production']['learned_meta']['primary'];
        $this->assertSame('Learned from Main X32 · Scene 02', $primary);
        $this->assertDoesNotMatchRegularExpression('/Scene 1[^0-9]/', $primary);
    }

    #[Test]
    public function it_falls_back_to_requested_scene_number_when_summary_scene_number_missing(): void
    {
        $detail = app(X32RoutingWorkspaceBuilder::class)->buildConfigurationDetailRow(
            $this->sampleNormalizedRouting(),
            [
                'baseline_saved_at' => now(),
                'device_name' => 'Main X32',
                'requested_scene_number' => '05',
            ],
        );

        $this->assertSame('Learned from Main X32 · Scene 05', $detail['production']['learned_meta']['primary']);
    }

    #[Test]
    public function it_builds_routing_flow_row_with_learned_console_and_main_lr(): void
    {
        $flow = app(X32RoutingWorkspaceBuilder::class)->buildRoutingFlowRow(
            $this->withSourceConnectivity($this->sampleNormalizedRouting([
                'main_lr' => [
                    'state' => 'learned',
                    'learned' => true,
                    'left' => ['output_number' => 3, 'raw_label' => 'Main L'],
                    'right' => ['output_number' => 4, 'raw_label' => 'Main R'],
                ],
            ]), [
                'stagebox_a' => ['state' => 'online'],
                'stagebox_b' => ['state' => 'online'],
                'ableton' => ['state' => 'online'],
            ]),
        );

        $this->assertSame('learned', $flow['routing_state']['state']);
        $this->assertSame('Ready', $flow['sources'][0]['status_label']);
        $this->assertSame('ready', $flow['sources'][0]['status']);
        $this->assertSame('Ready', $flow['sources'][2]['status_label']);
        $this->assertSame('Routed via AES50A', $flow['sources'][0]['routing']['label']);
        $this->assertSame('Online', $flow['sources'][0]['connectivity']['label']);
        $this->assertSame('Channel routing OK', $flow['console']['status_label']);
        $this->assertSame('ok', $flow['console']['status']);
        $this->assertStringContainsString('CH01', $flow['console']['channel_range']);
        $this->assertSame('Output resolved', $flow['destinations'][0]['status_label']);
        $this->assertStringContainsString('Out 3', $flow['destinations'][0]['lines'][0]['value']);
        $this->assertSame('IEM / Return Buses', $flow['destinations'][1]['title']);
        $this->assertSame('Not configured', $flow['destinations'][1]['status_label']);
    }

    #[Test]
    public function it_builds_source_row_with_expected_setup_when_not_learned(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow([
            'routing' => [],
        ]);

        $this->assertSame('Not routed', $sourceRow['cards'][0]['status_label']);
        $this->assertSame('not_routed', $sourceRow['cards'][0]['status']);
        $this->assertSame('Expected setup', $sourceRow['cards'][0]['routing']['label']);
        $this->assertSame('Assignment', $sourceRow['cards'][0]['routing_prefix']);
    }

    #[Test]
    public function it_exposes_routing_assignment_separately_from_operational_result(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow(
            $this->sampleNormalizedRouting(),
        );

        $stageboxA = $sourceRow['cards'][0];

        $this->assertSame('Disconnected', $stageboxA['status_label']);
        $this->assertSame('Routed via AES50A', $stageboxA['routing']['label']);
        $this->assertSame('Status not monitored yet', $stageboxA['connectivity']['label']);
        $this->assertSame('Routed via AES50B', $sourceRow['cards'][1]['routing']['label']);
        $this->assertSame('Routed via USB/Card', $sourceRow['cards'][2]['routing']['label']);
        $this->assertStringNotContainsString('Suggested', $stageboxA['status_label']);
        $this->assertStringNotContainsString('Connected', $stageboxA['status_label']);
        $this->assertNotSame('Ready', $stageboxA['status_label']);
    }

    #[Test]
    public function it_reports_ready_only_when_routed_and_connectivity_is_online(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow(
            $this->withSourceConnectivity($this->sampleNormalizedRouting(), [
                'stagebox_a' => ['state' => 'online'],
            ]),
        );

        $this->assertSame('Ready', $sourceRow['cards'][0]['status_label']);
        $this->assertSame('Online', $sourceRow['cards'][0]['connectivity']['label']);
        $this->assertSame('Routed via AES50A', $sourceRow['cards'][0]['routing']['label']);
    }

    #[Test]
    public function it_reports_source_offline_when_routed_and_aes50_connectivity_is_offline(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow(
            $this->withSourceConnectivity($this->sampleNormalizedRouting(), [
                'stagebox_a' => ['state' => 'offline'],
                'stagebox_b' => ['state' => 'offline'],
            ]),
        );

        $this->assertSame('Source offline', $sourceRow['cards'][0]['status_label']);
        $this->assertSame('Offline', $sourceRow['cards'][0]['connectivity']['label']);
        $this->assertSame('Source offline', $sourceRow['cards'][1]['status_label']);
    }

    #[Test]
    public function it_reports_disconnected_for_routed_aes50_without_connectivity_data(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow(
            $this->sampleNormalizedRouting(),
        );

        $this->assertSame('Disconnected', $sourceRow['cards'][0]['status_label']);
        $this->assertSame('Status not monitored yet', $sourceRow['cards'][0]['connectivity']['label']);
        $this->assertFalse($sourceRow['cards'][0]['connectivity']['monitored']);
    }

    #[Test]
    public function it_reports_ableton_card_not_available_when_offline(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow(
            $this->withSourceConnectivity($this->sampleNormalizedRouting(), [
                'ableton' => ['state' => 'offline'],
            ]),
        );

        $this->assertSame('Ableton/Card not available', $sourceRow['cards'][2]['status_label']);
        $this->assertSame('Offline', $sourceRow['cards'][2]['connectivity']['label']);
    }

    #[Test]
    public function it_reports_disconnected_for_routed_usb_card_without_connectivity_data(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow(
            $this->sampleNormalizedRouting(),
        );

        $this->assertSame('Disconnected', $sourceRow['cards'][2]['status_label']);
        $this->assertSame('Routed via USB/Card', $sourceRow['cards'][2]['routing']['label']);
    }

    #[Test]
    public function it_marks_stagebox_routing_as_routed_via_aes50_from_normalized_input_banks(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow(
            $this->sampleNormalizedRouting(),
        );

        $this->assertSame('Routed via AES50A', $sourceRow['cards'][0]['routing']['label']);
        $this->assertSame('Routed via AES50B', $sourceRow['cards'][1]['routing']['label']);
        $this->assertSame('Routed via USB/Card', $sourceRow['cards'][2]['routing']['label']);
        $this->assertSame('Assignment', $sourceRow['cards'][0]['routing_prefix']);
        $this->assertStringNotContainsString('Suggested', $sourceRow['cards'][0]['status_label']);
        $this->assertStringNotContainsString('Connected', $sourceRow['cards'][0]['status_label']);
    }

    #[Test]
    public function it_shows_not_routed_when_normalized_routing_exists_but_source_is_unused(): void
    {
        $routing = $this->sampleNormalizedRouting([
            'input_banks' => [
                [
                    'bank' => '1-8',
                    'console_channel_range' => 'CH 01–08',
                    'console_channels' => range(1, 8),
                    'source_type' => 'aes50_a',
                    'source_range' => 'A1-8',
                    'raw_label' => 'A1-8',
                    'learned' => true,
                ],
            ],
        ]);

        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow($routing);

        $this->assertSame('Disconnected', $sourceRow['cards'][0]['status_label']);
        $this->assertSame('Routed via AES50A', $sourceRow['cards'][0]['routing']['label']);
        $this->assertSame('Not routed', $sourceRow['cards'][1]['status_label']);
        $this->assertSame('Not routed', $sourceRow['cards'][2]['status_label']);
    }

    #[Test]
    public function it_builds_operator_production_zones_with_not_learned_defaults(): void
    {
        $workspace = app(X32RoutingWorkspaceBuilder::class)->build([
            'channels' => array_fill(0, 32, ['name' => 'Ch']),
            'routing' => [],
        ]);

        $this->assertSame('Not learned', $workspace['input_sources']['stagebox_a']['connection']);
        $this->assertSame('Expected setup', $workspace['input_sources']['stagebox_a']['operator_label']);
        $this->assertSame('suggested', $workspace['input_sources']['stagebox_a']['routing_lines'][0]['state']);
        $this->assertSame('not_learned', $workspace['outputs']['foh']['state']);
    }

    #[Test]
    public function it_surfaces_main_lr_only_from_normalized_routing(): void
    {
        $workspace = app(X32RoutingWorkspaceBuilder::class)->build(
            $this->sampleNormalizedRouting([
                'main_lr' => [
                    'state' => 'learned',
                    'learned' => true,
                    'left' => ['output_number' => 3, 'raw_label' => 'Main L'],
                    'right' => ['output_number' => 4, 'raw_label' => 'Main R'],
                ],
            ]),
        );

        $this->assertSame('Main L', $workspace['outputs']['foh']['left']['source']);
        $this->assertSame('Out 3', $workspace['outputs']['foh']['left']['output']);
        $this->assertSame('learned', $workspace['outputs']['foh']['state']);
    }

    #[Test]
    public function it_does_not_invent_main_lr_from_legacy_top_level_values(): void
    {
        $workspace = app(X32RoutingWorkspaceBuilder::class)->build([
            'channels' => [],
            'routing' => [
                'main_lr' => ['left' => 'BUS 15', 'right' => 'BUS 16'],
                'normalized' => [
                    'main_lr' => [
                        'state' => 'not_learned',
                        'left' => null,
                        'right' => null,
                        'learned' => false,
                    ],
                ],
            ],
        ]);

        $this->assertSame('not_learned', $workspace['outputs']['foh']['state']);
        $this->assertSame('Not learned', $workspace['outputs']['foh']['left']['source']);
        $this->assertSame('Output not resolved', $workspace['outputs']['foh']['operator_label']);
    }

    #[Test]
    public function it_uses_learned_channel_sources_from_normalized_input_banks(): void
    {
        $workspace = app(X32RoutingWorkspaceBuilder::class)->build(
            array_merge($this->sampleNormalizedRouting(), [
                'channels' => array_merge([['name' => 'Kick']], array_fill(0, 31, ['name' => ''])),
            ]),
        );

        $this->assertSame('A1-8', $workspace['channel_allocation'][0]['source_type']);
        $this->assertSame('learned', $workspace['channel_allocation'][0]['state']);
        $this->assertSame('stagebox_a', $workspace['channel_allocation'][0]['group']);
        $this->assertSame('Kick', $workspace['channel_allocation'][0]['name']);
    }

    #[Test]
    public function it_exposes_learned_out_1_16_blocks_in_outputs_detail(): void
    {
        $detail = app(X32RoutingWorkspaceBuilder::class)->buildConfigurationDetailRow(
            $this->sampleNormalizedRouting(),
        );

        $this->assertSame('learned', $detail['outputs']['out_1_16']['state']);
        $this->assertCount(4, $detail['outputs']['out_1_16']['blocks']);
    }

    #[Test]
    public function expected_fallback_does_not_mark_routing_as_fully_learned(): void
    {
        $flow = app(X32RoutingWorkspaceBuilder::class)->buildRoutingFlowRow([
            'routing' => [],
        ]);

        $this->assertSame('not_learned', $flow['routing_state']['state']);
        $this->assertSame('Awaiting console routing learn', $flow['routing_state']['label']);
        $this->assertSame('Not routed', $flow['sources'][0]['status_label']);
        $this->assertSame('Expected setup', $flow['sources'][0]['routing']['label']);
        $this->assertSame('No routing learned', $flow['console']['status_label']);
    }

    #[Test]
    public function it_reports_partial_console_routing_when_only_some_channels_are_mapped(): void
    {
        $flow = app(X32RoutingWorkspaceBuilder::class)->buildRoutingFlowRow(
            $this->sampleNormalizedRouting([
                'input_banks' => [
                    [
                        'bank' => '1-8',
                        'console_channel_range' => 'CH 01–08',
                        'console_channels' => range(1, 8),
                        'source_type' => 'aes50_a',
                        'source_range' => 'A1-8',
                        'raw_label' => 'A1-8',
                        'learned' => true,
                    ],
                ],
            ]),
        );

        $this->assertSame('Partial routing', $flow['console']['status_label']);
        $this->assertSame('partial', $flow['console']['status']);
    }

    #[Test]
    public function it_shows_buses_configured_when_bus_data_exists_without_iem_output_routing(): void
    {
        $detail = app(X32RoutingWorkspaceBuilder::class)->buildConfigurationDetailRow([
            'routing' => ['normalized' => []],
            'buses' => array_map(
                fn (int $number): array => ['number' => $number, 'name' => sprintf('Monitor %d', $number)],
                range(1, 12),
            ),
        ]);

        $this->assertSame('IEM / Return Buses', $detail['outputs']['iems']['title']);
        $this->assertSame('12 buses configured', $detail['outputs']['iems']['summary']);
        $this->assertSame('Output routing not resolved yet', $detail['outputs']['iems']['detail_line']);
        $this->assertSame('12 buses configured', $detail['production']['status_grid'][4]['status_label']);
        $this->assertCount(3, $detail['outputs']['iems']['columns']);
        $this->assertCount(4, $detail['outputs']['iems']['columns'][0]);
        $this->assertSame(1, $detail['outputs']['iems']['columns'][0][0]['number']);
        $this->assertSame('Monitor 1', $detail['outputs']['iems']['columns'][0][0]['name']);
        $this->assertSame(5, $detail['outputs']['iems']['columns'][1][0]['number']);
        $this->assertSame(9, $detail['outputs']['iems']['columns'][2][0]['number']);
    }

    #[Test]
    public function it_layouts_iem_flow_card_buses_in_three_columns(): void
    {
        $flow = app(X32RoutingWorkspaceBuilder::class)->buildRoutingFlowRow([
            'routing' => ['normalized' => []],
            'buses' => array_map(
                fn (int $number): array => ['number' => $number, 'name' => sprintf('Monitor %d', $number)],
                range(1, 12),
            ),
        ]);

        $iems = $flow['destinations'][1];

        $this->assertSame('partial', $iems['status']);
        $this->assertCount(3, $iems['columns']);
        $this->assertSame('Output routing not resolved yet', $iems['summary']);
        $this->assertSame(12, $iems['columns'][2][3]['number']);
        $this->assertSame('Monitor 12', $iems['columns'][2][3]['name']);
    }

    #[Test]
    public function it_includes_future_production_configuration_names(): void
    {
        $workspace = app(X32RoutingWorkspaceBuilder::class)->build(['routing' => []]);

        $names = array_column($workspace['future_configurations'], 'name');

        $this->assertSame(
            ['Duo', 'LoFi Setup', 'Full Band Setup', '4 Piece Rock Band', 'Custom'],
            $names,
        );
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, array{state: string}>  $connectivity
     * @return array<string, mixed>
     */
    private function withSourceConnectivity(array $summary, array $connectivity): array
    {
        $summary['routing']['source_connectivity'] = $connectivity;

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $normalizedOverrides
     * @return array<string, mixed>
     */
    private function sampleNormalizedRouting(array $normalizedOverrides = []): array
    {
        $normalized = array_merge([
            'input_banks' => [
                [
                    'bank' => '1-8',
                    'console_channel_range' => 'CH 01–08',
                    'console_channels' => range(1, 8),
                    'source_type' => 'aes50_a',
                    'source_range' => 'A1-8',
                    'raw_label' => 'A1-8',
                    'learned' => true,
                ],
                [
                    'bank' => '9-16',
                    'console_channel_range' => 'CH 09–16',
                    'console_channels' => range(9, 16),
                    'source_type' => 'aes50_a',
                    'source_range' => 'A9-16',
                    'raw_label' => 'A9-16',
                    'learned' => true,
                ],
                [
                    'bank' => '17-24',
                    'console_channel_range' => 'CH 17–24',
                    'console_channels' => range(17, 24),
                    'source_type' => 'aes50_b',
                    'source_range' => 'B1-8',
                    'raw_label' => 'B1-8',
                    'learned' => true,
                ],
                [
                    'bank' => '25-32',
                    'console_channel_range' => 'CH 25–32',
                    'console_channels' => range(25, 32),
                    'source_type' => 'card_usb',
                    'source_range' => 'CARD1-8',
                    'raw_label' => 'CARD1-8',
                    'learned' => true,
                ],
            ],
            'card_inputs' => [
                [
                    'context' => 'input_bank',
                    'card_range' => 'CARD1-8',
                    'desk_channel_range' => 'CH 25–32',
                ],
            ],
            'out_1_16' => [
                ['block' => '1-4', 'output_range' => 'Out 1–4', 'source_range' => 'AN1-4', 'raw_label' => 'AN1-4', 'source_type' => 'local'],
                ['block' => '5-8', 'output_range' => 'Out 5–8', 'source_range' => 'AN5-8', 'raw_label' => 'AN5-8', 'source_type' => 'local'],
                ['block' => '9-12', 'output_range' => 'Out 9–12', 'source_range' => 'AN9-12', 'raw_label' => 'AN9-12', 'source_type' => 'local'],
                ['block' => '13-16', 'output_range' => 'Out 13–16', 'source_range' => 'AN13-16', 'raw_label' => 'AN13-16', 'source_type' => 'local'],
            ],
            'main_lr' => [
                'state' => 'not_learned',
                'left' => null,
                'right' => null,
                'learned' => false,
            ],
        ], $normalizedOverrides);

        return [
            'routing' => [
                'source' => 'test',
                'normalized' => $normalized,
            ],
        ];
    }
}
