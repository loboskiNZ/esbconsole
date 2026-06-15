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
        ]);

        $this->assertSame('Configuration Actions', $bottom['configuration_actions']['title']);
        $this->assertCount(5, $bottom['configuration_actions']['steps']);
        $this->assertSame('Learn From Console', $bottom['configuration_actions']['steps'][0]['label']);
        $this->assertSame('Available', $bottom['configuration_actions']['steps'][0]['status_label']);
        $this->assertSame('Not available yet', $bottom['configuration_actions']['steps'][1]['status_label']);
        $this->assertSame('Coming later', $bottom['configuration_actions']['steps'][2]['status_label']);
        $this->assertSame('Advanced X32 Routing', $bottom['advanced']['title']);
        $this->assertCount(8, $bottom['advanced']['categories']);
        $this->assertFalse($bottom['advanced']['action_available']);
    }

    #[Test]
    public function it_builds_configuration_detail_row_with_three_columns(): void
    {
        $detail = app(X32RoutingWorkspaceBuilder::class)->buildConfigurationDetailRow([
            'channels' => array_fill(0, 32, ['name' => '']),
            'routing' => [
                'main_lr' => ['left' => 'BUS 15', 'right' => 'BUS 16'],
            ],
        ], [
            'baseline_name' => 'Scene Baseline',
            'baseline_saved_at' => now()->subMinutes(2),
            'device_name' => 'Main X32',
        ]);

        $this->assertSame('Current Production Configuration', $detail['production']['title']);
        $this->assertSame('Scene Baseline', $detail['production']['name']);
        $this->assertSame('Learned from Main X32', $detail['production']['learned_meta']['primary']);
        $this->assertCount(5, $detail['production']['status_grid']);
        $this->assertCount(3, $detail['input_sources']['cards']);
        $this->assertSame('Ableton', $detail['input_sources']['cards'][2]['title']);
        $this->assertSame('USB/Card', $detail['input_sources']['cards'][2]['connection_type']);
        $this->assertSame('Expected', $detail['input_sources']['cards'][0]['connection_status']['label']);
        $this->assertSame('Assigned below', $detail['input_sources']['cards'][0]['secondary_note']);
        $this->assertSame('Returns assigned below', $detail['input_sources']['cards'][2]['secondary_note']);
        $this->assertCount(32, $detail['input_sources']['channel_allocation']['channels']);
        $this->assertSame('Outputs', $detail['outputs']['title']);
        $this->assertArrayNotHasKey('routing_lines', $detail['input_sources']['cards'][0]);
    }

    #[Test]
    public function it_builds_routing_flow_row_with_console_and_destinations(): void
    {
        $flow = app(X32RoutingWorkspaceBuilder::class)->buildRoutingFlowRow([
            'routing' => [
                'main_lr' => ['left' => 'BUS 15', 'right' => 'BUS 16'],
            ],
        ]);

        $this->assertSame('Routing Flow', $flow['label']);
        $this->assertCount(3, $flow['sources']);
        $this->assertSame('Console Channels', $flow['console']['title']);
        $this->assertSame('CH01–CH32', $flow['console']['channel_range']);
        $this->assertSame('FOH', $flow['destinations'][0]['title']);
        $this->assertSame('IEMs', $flow['destinations'][1]['title']);
        $this->assertSame('Suggested', $flow['destinations'][1]['status_label']);
    }

    #[Test]
    public function it_builds_source_row_with_suggested_defaults_when_not_learned(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow([
            'routing' => [],
        ]);

        $this->assertSame('Stagebox A', $sourceRow['cards'][0]['title']);
        $this->assertSame('Suggested', $sourceRow['cards'][0]['status_label']);
        $this->assertSame('AES50A', $sourceRow['cards'][0]['connection']);
        $this->assertSame('CH01–CH16', $sourceRow['cards'][0]['routing_line']);
        $this->assertSame('CH17–CH24', $sourceRow['cards'][1]['routing_line']);
        $this->assertSame('CH25–CH32', $sourceRow['cards'][2]['routing_line']);
    }

    #[Test]
    public function it_marks_stagebox_routing_as_learned_when_input_banks_exist(): void
    {
        $sourceRow = app(X32RoutingWorkspaceBuilder::class)->buildSourceRow([
            'routing' => [
                'input_banks' => [
                    ['source_type' => 'AES50A', 'channels' => 'CH 01–16'],
                ],
                'stagebox_a' => [
                    'desk_channels' => 'CH01–CH16',
                ],
            ],
        ]);

        $this->assertSame('Learned', $sourceRow['cards'][0]['status_label']);
        $this->assertSame('Routed to', $sourceRow['cards'][0]['routing_prefix']);
    }

    #[Test]
    public function it_builds_operator_production_zones_with_not_learned_defaults(): void
    {
        $workspace = app(X32RoutingWorkspaceBuilder::class)->build([
            'channels' => array_fill(0, 32, ['name' => 'Ch']),
            'routing' => [
                'source' => 'fixture',
                'main_lr' => ['left' => 'BUS 15', 'right' => 'BUS 16'],
            ],
        ]);

        $this->assertSame('Stagebox A', $workspace['input_sources']['stagebox_a']['label']);
        $this->assertSame('Not learned', $workspace['input_sources']['stagebox_a']['connection']);
        $this->assertSame('suggested', $workspace['input_sources']['stagebox_a']['routing_lines'][0]['state']);
        $this->assertSame('Unknown / Not learned', $workspace['production_configuration']['type']);
        $this->assertCount(32, $workspace['channel_allocation']);
    }

    #[Test]
    public function it_surfaces_partial_main_lr_for_foh_without_faking_xlr_outputs(): void
    {
        $workspace = app(X32RoutingWorkspaceBuilder::class)->build([
            'channels' => [],
            'routing' => [
                'main_lr' => ['left' => 'BUS 15', 'right' => 'BUS 16'],
            ],
        ]);

        $this->assertSame('BUS 15', $workspace['outputs']['foh']['left']['source']);
        $this->assertSame('partial', $workspace['outputs']['foh']['state']);
        $this->assertSame('Unassigned', $workspace['outputs']['spare'][0]['assignment']);
    }

    #[Test]
    public function it_uses_learned_channel_sources_when_present(): void
    {
        $workspace = app(X32RoutingWorkspaceBuilder::class)->build([
            'channels' => [['name' => 'Kick']],
            'routing' => [
                'channel_sources' => [
                    1 => [
                        'source_type' => 'Stagebox A',
                        'source_socket' => 'AES50A-01',
                        'purpose' => 'Kick drum',
                    ],
                ],
            ],
        ]);

        $this->assertSame('Stagebox A', $workspace['channel_allocation'][0]['source_type']);
        $this->assertSame('learned', $workspace['channel_allocation'][0]['state']);
        $this->assertSame('Not learned', $workspace['channel_allocation'][1]['source_type']);
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

    #[Test]
    public function it_labels_ableton_returns_as_suggested_when_not_learned(): void
    {
        $workspace = app(X32RoutingWorkspaceBuilder::class)->build(['routing' => []]);

        $this->assertCount(8, $workspace['input_sources']['ableton']['returns']);
        $this->assertSame('suggested', $workspace['input_sources']['ableton']['returns'][0]['state']);
        $this->assertStringContainsString('CH 25', $workspace['input_sources']['ableton']['returns'][0]['desk_channel']);
    }
}
