<?php

namespace Tests\Unit;

use App\Services\Console\X32MonitorBusMasterEqCardBuilder;
use App\Services\Console\X32MonitorsWorkspaceBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32MonitorsWorkspaceBuilderTest extends TestCase
{
    private X32MonitorsWorkspaceBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new X32MonitorsWorkspaceBuilder(new X32MonitorBusMasterEqCardBuilder);
    }

    #[Test]
    public function it_excludes_main_lr_from_sidebar_buses(): void
    {
        $workspace = $this->builder->build($this->summaryWithBuses([
            ['number' => 1, 'name' => ['value' => 'Ed IEM', 'state' => 'learned']],
            ['number' => 2, 'name' => ['value' => 'Main LR', 'state' => 'learned']],
            ['number' => 3, 'name' => ['value' => 'Mains', 'state' => 'learned']],
        ]), 1);

        $numbers = array_column($workspace['sidebar']['buses'], 'number');

        $this->assertSame([1], $numbers);
        $this->assertSame('Ed IEM', $workspace['active_bus_name']);
        $this->assertSame('Ed IEM — EQ', $workspace['eq']['title']);
    }

    #[Test]
    public function it_uses_learned_channel_names_and_fallbacks(): void
    {
        $workspace = $this->builder->build($this->summaryWithBuses([
            ['number' => 1, 'name' => ['value' => 'Ed IEM', 'state' => 'learned']],
        ], channels: [
            ['number' => 1, 'name' => ['value' => 'Kick', 'state' => 'learned']],
            ['number' => 2, 'name' => ['value' => 'CH 02', 'state' => 'learned']],
        ]), 1);

        $this->assertSame('Kick', $workspace['channels']['strips'][0]['display_name']);
        $this->assertSame('CH 2', $workspace['channels']['strips'][1]['display_name']);
        $this->assertCount(32, $workspace['channels']['strips']);
    }

    #[Test]
    public function it_scopes_mute_labels_to_selected_monitor_bus(): void
    {
        $workspace = $this->builder->build($this->summaryWithBuses([
            ['number' => 1, 'name' => ['value' => 'Ed IEM', 'state' => 'learned']],
        ]), 1);

        $this->assertSame(
            'Monitor mute · Ed IEM · CH 1',
            $workspace['channels']['strips'][0]['mute_scope_label'],
        );
    }

    #[Test]
    public function it_builds_channel_settings_empty_state_when_no_channel_selected(): void
    {
        $workspace = $this->builder->build($this->summaryWithBuses([
            ['number' => 1, 'name' => ['value' => 'Ed IEM', 'state' => 'learned']],
        ]), 1);

        $this->assertTrue($workspace['channel_settings']['empty']);
        $this->assertSame(
            'Select a channel to edit monitor-send settings for Ed IEM.',
            $workspace['channel_settings']['empty_message'],
        );
    }

    #[Test]
    public function it_builds_monitor_send_groups_not_dcas(): void
    {
        $workspace = $this->builder->build($this->summaryWithBuses([
            ['number' => 1, 'name' => ['value' => 'Ed IEM', 'state' => 'learned']],
        ]), 1);

        $this->assertSame('Group Control', $workspace['group_control']['title']);
        $this->assertSame('All Channels', $workspace['group_control']['all_channels_label']);
        $this->assertSame('All Channels', $workspace['group_control']['all_channels_view_label']);
        $this->assertSame('Group View', $workspace['group_control']['group_view_label']);
        $this->assertSame('Clear group', $workspace['group_control']['clear_group_label']);
        $this->assertSame(
            ['Drumkit', 'Bass', 'Guitars', 'Keys', 'Vocals', 'Horns', 'Tracks', 'Talkback'],
            array_column($workspace['group_control']['groups'], 'label'),
        );
        $this->assertSame([], $workspace['group_control']['groups'][0]['channels']);
        $this->assertSame([], $workspace['channels']['strips'][0]['group_keys']);
    }

    #[Test]
    public function it_scopes_bus_master_to_selected_bus(): void
    {
        $workspace = $this->builder->build($this->summaryWithBuses([
            [
                'number' => 1,
                'name' => ['value' => 'Ed IEM', 'state' => 'learned'],
                'fader' => ['value' => 0.75, 'state' => 'learned'],
                'mute' => ['value' => false, 'state' => 'learned'],
            ],
        ]), 1);

        $this->assertSame('Bus Master', $workspace['bus_master']['title']);
        $this->assertSame('Master level for Ed IEM only.', $workspace['bus_master']['scope_hint']);
        $this->assertSame('Ed IEM', $workspace['bus_master']['bus_name']);
    }

    #[Test]
    public function it_maps_learned_x32_channel_colours_to_monitor_strips(): void
    {
        $workspace = $this->builder->build($this->summaryWithBuses([
            ['number' => 1, 'name' => ['value' => 'Ed IEM', 'state' => 'learned']],
        ], channels: [
            ['number' => 1, 'name' => ['value' => 'Kick', 'state' => 'learned'], 'colour' => ['value' => 1, 'state' => 'learned']],
            ['number' => 2, 'name' => ['value' => 'Snare', 'state' => 'learned'], 'colour' => ['value' => 6, 'state' => 'learned']],
        ]), 1);

        $kick = $workspace['channels']['strips'][0];
        $snare = $workspace['channels']['strips'][1];
        $default = $workspace['channels']['strips'][2];

        $this->assertTrue($kick['color_learned']);
        $this->assertSame(1, $kick['color_index']);
        $this->assertSame('#c03030', $kick['color_css']);
        $this->assertSame('Red', $kick['color_label']);

        $this->assertTrue($snare['color_learned']);
        $this->assertSame(6, $snare['color_index']);
        $this->assertSame('#30c0c0', $snare['color_css']);

        $this->assertFalse($default['color_learned']);
        $this->assertSame(0, $default['color_index']);
        $this->assertSame('#3f3f46', $default['color_css']);
    }

    #[Test]
    public function it_renders_learned_monitor_send_levels_for_selected_bus(): void
    {
        $workspace = $this->builder->build($this->summaryWithBuses([
            ['number' => 1, 'name' => ['value' => 'Ed IEM', 'state' => 'learned']],
        ], channels: [
            [
                'number' => 1,
                'name' => ['value' => 'Kick', 'state' => 'learned'],
                'sends' => [
                    'buses' => [
                        '1' => [
                            'level' => [
                                'value' => ['linear' => 0.75, 'value' => 0.0, 'unit' => 'dB'],
                                'state' => 'learned',
                                'source' => '/ch/01/mix/01/level',
                            ],
                            'on' => ['value' => true, 'state' => 'learned', 'source' => '/ch/01/mix/01/on'],
                            'tap' => ['value' => 'post_fader', 'state' => 'learned', 'source' => '/ch/01/mix/01/type'],
                        ],
                    ],
                ],
            ],
        ]), 1);

        $kick = $workspace['channels']['strips'][0];
        $default = $workspace['channels']['strips'][1];

        $this->assertTrue($kick['send_learned']);
        $this->assertSame('learned', $kick['send_state']);
        $this->assertEqualsWithDelta(0.0, $kick['level_db'], 0.1);
        $this->assertSame('0.0', $kick['level_display']);
        $this->assertFalse($kick['mute']);
        $this->assertFalse($default['send_learned']);
        $this->assertSame('—', $default['level_display']);
        $this->assertSame('placeholder', $default['send_state']);
    }

    #[Test]
    public function it_keeps_group_control_scaffold_honest(): void
    {
        $workspace = $this->builder->build($this->summaryWithBuses([
            ['number' => 1, 'name' => ['value' => 'Ed IEM', 'state' => 'learned']],
        ]), 1);

        $this->assertStringContainsString(
            'not learned from the X32',
            (string) ($workspace['group_control']['scaffold_notice'] ?? ''),
        );
    }

    #[Test]
    public function it_rejects_invalid_bus_numbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder->build([], 17);
    }

    /**
     * @param  list<array<string, mixed>>  $buses
     * @param  list<array<string, mixed>>  $channels
     * @return array<string, mixed>
     */
    private function summaryWithBuses(array $buses, array $channels = []): array
    {
        if ($channels === []) {
            for ($number = 1; $number <= 32; $number++) {
                $channels[] = ['number' => $number];
            }
        }

        return [
            'configuration' => [
                'identity' => [],
                'channels' => $channels,
                'buses' => $buses,
                'warnings' => [],
            ],
        ];
    }
}
