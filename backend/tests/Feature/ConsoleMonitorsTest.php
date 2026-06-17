<?php

namespace Tests\Feature;

use App\Enums\ConsoleLearningStatus;
use App\Models\Band;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\Console\ShowConsoleBaselineService;
use App\Services\Console\X32ConsoleLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleMonitorsTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_bus_workspace_route_renders(): void
    {
        $show = $this->showWithBaseline();

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->assertSee('vx32-monitors-workspace', false);
    }

    public function test_legacy_monitors_route_redirects_to_canonical_bus_layout(): void
    {
        $show = $this->showWithBaseline();

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.monitors', [$show, 2]))
            ->assertRedirect(route('shows.console.bus.layout', [$show, 2]));
    }

    public function test_bus_workspace_redirects_when_no_baseline(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'No Baseline']);
        $this->createX32Device($band);

        $this->actingAs($user)
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertRedirect(route('shows.console.learn', $show));
    }

    public function test_selected_bus_controls_page_and_card_titles(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(5, 'Sarah IEM'));

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 5]))
            ->assertOk()
            ->assertSee('Sarah IEM', false)
            ->assertSee('Sarah IEM — EQ', false)
            ->assertSee('Sarah IEM — Channel Settings', false)
            ->assertSee('Master level for Sarah IEM only.', false);
    }

    public function test_buses_sidebar_excludes_main_lr_and_links_to_selected_bus(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(1, 'Ed IEM', extraBuses: [
            ['number' => 2, 'name' => ['value' => 'Main LR', 'state' => 'learned']],
            ['number' => 3, 'name' => ['value' => 'Ed IEM 2', 'state' => 'learned']],
        ]));

        $response = $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]));

        $response->assertOk()
            ->assertSee('Buses (Monitors)', false)
            ->assertSee('vx32-monitors-bus-picker', false)
            ->assertSee('vx32-monitors-groups-menu', false)
            ->assertSee('data-group-menu-hint', false)
            ->assertSee('Ed IEM', false)
            ->assertSee('Ed IEM 2', false)
            ->assertSee(route('shows.console.bus.layout', [$show, 3], false), false)
            ->assertDontSee(route('shows.console.bus.layout', [$show, 2], false), false);
    }

    public function test_channels_card_renders_thirty_two_strips_with_learned_and_fallback_names(): void
    {
        $summary = $this->summaryWithNamedBus(1, 'Ed IEM');
        $summary['configuration']['channels'] = [
            ['number' => 1, 'name' => ['value' => 'Kick', 'state' => 'learned'], 'colour' => ['value' => 1, 'state' => 'learned']],
            ['number' => 2, 'name' => ['value' => 'CH 02', 'state' => 'learned']],
        ];

        for ($number = 3; $number <= 32; $number++) {
            $summary['configuration']['channels'][] = ['number' => $number];
        }

        $show = $this->showWithBaseline($summary);

        $response = $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]));

        $response->assertOk()
            ->assertSee('Channels', false)
            ->assertSee('Kick', false)
            ->assertSee('CH 2', false)
            ->assertSee('CH 32', false)
            ->assertSee('data-channel-color-index="1"', false)
            ->assertSee('--channel-color: #c03030', false)
            ->assertSee('vx32-monitors-channel-strip', false)
            ->assertSee('vx32-monitors-strip__scale-tick', false)
            ->assertSee('vx32-monitors-strip__track-unity', false)
            ->assertSee('>0<', false)
            ->assertSee('>−∞<', false);

        $this->assertSame(
            32,
            preg_match_all('/data-channel="\d+"/', $response->getContent()),
        );
    }

    public function test_mute_ui_is_monitor_scoped_not_foh_scoped(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(1, 'Ed IEM'));

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->assertSee('Monitor mute · Ed IEM', false)
            ->assertDontSee('FOH mute', false)
            ->assertDontSee('Monitor send levels for Ed IEM', false);
    }

    public function test_group_control_renders_monitor_send_groups_not_dcas(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(1, 'Ed IEM'));

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->assertSee('Group Control', false)
            ->assertSee('All Channels', false)
            ->assertSee('Drumkit', false)
            ->assertSee('Tracks', false)
            ->assertSee('Talkback', false)
            ->assertSee('Group View', false)
            ->assertSee('data-monitors-group-control', false)
            ->assertSee('data-group-select', false)
            ->assertSee('data-channels-view="all"', false)
            ->assertSee('data-channels-view="group"', false)
            ->assertSee('data-group-strip', false)
            ->assertSee('data-group-clear', false)
            ->assertSee('data-group-clear-active', false)
            ->assertSee('data-channel-strip', false)
            ->assertSee('data-group-pick-target', false)
            ->assertSee('data-channel-fader-control', false)
            ->assertSee('data-group-selection-status', false)
            ->assertSee('data-group-clear-pick', false)
            ->assertSee('data-group-remove-from', false)
            ->assertSee('data-group-control-badge', false)
            ->assertSee('Clear selection', false)
            ->assertSee('Remove from group', false)
            ->assertSee('Clear group', false)
            ->assertDontSee('Group Controls', false)
            ->assertSee('Group assignments are UI-only', false)
            ->assertDontSee('FOH group', false);
    }

    public function test_group_control_markup_supports_group_fader_and_channel_marking(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(1, 'Ed IEM'));

        $content = $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-group-key="drumkit"', $content);
        $this->assertStringContainsString('data-group-channels=""', $content);
        $this->assertStringContainsString('data-group-strip', $content);
        $this->assertStringContainsString('data-group-fader-handle', $content);
        $this->assertStringContainsString('data-group-fader-track', $content);
        $this->assertStringContainsString('data-channel="1"', $content);
        $this->assertStringContainsString('data-group-pick-target', $content);
        $this->assertStringContainsString('vx32-monitors-groups__pill--all is-active', $content);
        $this->assertStringNotContainsString('data-group-fader-panel', $content);
        $this->assertStringNotContainsString('data-group-keys="drumkit"', $content);
    }

    public function test_bus_master_is_selected_bus_scoped(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(1, 'Ed IEM'));

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->assertSee('Bus Master', false)
            ->assertSee('data-bus-master-strip', false)
            ->assertSee('vx32-monitors-bus-master-strip', false)
            ->assertSee('Monitor bus mute · Ed IEM', false)
            ->assertSee('Master level for Ed IEM only.', false)
            ->assertDontSee('Group Master', false)
            ->assertDontSee('monitors-bus-master-title', false);
    }

    public function test_channel_settings_empty_state_and_selection(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(1, 'Ed IEM'));

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->assertSee('Select a channel to edit monitor-send settings for Ed IEM.', false);

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1, 'channel' => 4]))
            ->assertOk()
            ->assertSee('4 · CH 4', false)
            ->assertSee('Monitor bus', false);
    }

    public function test_invalid_bus_number_returns_not_found(): void
    {
        $show = $this->showWithBaseline();

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 17]))
            ->assertNotFound();
    }

    public function test_configuration_warnings_are_not_copied_to_monitor_workspace(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(1, 'Ed IEM'));

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->assertDontSee('Routing table incomplete.', false);
    }

    public function test_eq_card_renders_with_bus_master_scope(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(1, 'Ed IEM'));

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->assertSee('Ed IEM — EQ', false)
            ->assertSee('Bus master EQ for Ed IEM', false)
            ->assertDontSee('Channel EQ', false)
            ->assertDontSee('Main LR EQ', false)
            ->assertDontSee('FOH EQ', false)
            ->assertDontSee('send EQ', false);
    }

    public function test_channels_card_renders_learned_send_levels_from_configuration_learn(): void
    {
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Learned Sends Show']);
        $device = $this->createX32Device($band);

        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $kick = collect($snapshot->learned_summary_json['configuration']['channels'] ?? [])
            ->firstWhere('number', 1);

        $this->assertIsArray($kick);
        $this->assertSame('learned', $kick['sends']['buses']['1']['level']['state']);

        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Learned Sends Baseline');

        $baseline = ShowConsoleBaseline::query()->where('show_id', $show->id)->where('active', true)->firstOrFail();
        $workspace = app(\App\Services\Console\X32MonitorsWorkspaceBuilder::class)->build($baseline->baseline_json ?? [], 1);
        $this->assertTrue($workspace['channels']['strips'][0]['send_learned']);
        $this->assertSame('Kick', $workspace['channels']['strips'][0]['display_name']);

        $response = $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Monitor mute · Bus 1 · CH 1', $content);
        $this->assertStringContainsString('Kick', $content);
        $this->assertStringContainsString('data-channel-fader-level>0.0</span>', $content);
        $this->assertStringContainsString('Group assignments are UI-only', $content);
    }

    public function test_eq_card_renders_learned_bus_eq_from_configuration_learn(): void
    {
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Learned EQ Show']);
        $device = $this->createX32Device($band);

        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $busOne = collect($snapshot->learned_summary_json['configuration']['buses'] ?? [])
            ->firstWhere('number', 1);

        $this->assertIsArray($busOne);
        $this->assertArrayHasKey('eq', $busOne);
        $this->assertSame('learned', $busOne['eq']['bands'][0]['frequency_hz']['state']);
        $this->assertEqualsWithDelta(79.6, $busOne['eq']['bands'][0]['frequency_hz']['value'], 0.1);

        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Learned EQ Baseline');

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->assertSee('Learned from console', false)
            ->assertSee('value="LSHV"', false)
            ->assertSee('value="79.6"', false)
            ->assertSee('Visual approximation only — not DSP accurate.', false)
            ->assertSee('data-eq-graph-full-width="true"', false)
            ->assertSee('data-eq-bands-below-graph="true"', false)
            ->assertDontSee('Placeholder EQ display only. Values shown are not from the X32.', false);
    }

    public function test_eq_card_shows_placeholder_state_when_not_learned(): void
    {
        $show = $this->showWithBaseline($this->summaryWithNamedBus(1, 'Ed IEM'));

        $response = $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]));

        $response->assertOk()
            ->assertSee('EQ not learned from console yet', false)
            ->assertSee('Placeholder EQ display only. Values shown are not from the X32.', false)
            ->assertSee('Visual approximation only — not DSP accurate.', false)
            ->assertSee('20Hz', false)
            ->assertSee('+15dB', false)
            ->assertSee('data-eq-graph-full-width="true"', false)
            ->assertSee('vx32-monitors-eq__graph-y-axis', false)
            ->assertSee('vx32-monitors-eq__graph-x-axis', false)
            ->assertSee('data-eq-bands-below-graph="true"', false)
            ->assertSee('vx32-monitors-eq__bands-row', false)
            ->assertSee('data-eq-band-strip', false)
            ->assertSee('data-eq-workspace', false)
            ->assertSee('data-eq-handle', false)
            ->assertSee('data-eq-panel-toggle', false)
            ->assertSee('data-eq-mode-select', false)
            ->assertSee('value="HCUT"', false)
            ->assertSee('value="LCUT"', false)
            ->assertSee('value="79.6"', false)
            ->assertSee('value="1K97"', false)
            ->assertSee('value="0"', false)
            ->assertSee('Low', false)
            ->assertSee('high mid', false)
            ->assertSee('vx32-monitors-eq__field-input', false)
            ->assertDontSee('vx32-monitors-eq__table', false);

        $content = $response->getContent();
        $graphPos = strpos($content, 'data-eq-graph-full-width="true"');
        $bandsPos = strpos($content, 'data-eq-bands-below-graph="true"');
        $this->assertNotFalse($graphPos);
        $this->assertNotFalse($bandsPos);
        $this->assertLessThan($bandsPos, $graphPos);
    }

    public function test_eq_card_shows_learned_values_when_available(): void
    {
        $summary = $this->summaryWithNamedBus(1, 'Ed IEM');
        $summary['configuration']['buses'][0]['eq'] = [
            'on' => ['value' => true, 'state' => 'learned'],
            'low_cut' => [
                'on' => ['value' => true, 'state' => 'learned'],
                'frequency_hz' => ['value' => 100, 'state' => 'learned'],
                'mode' => ['value' => 'Low Cut', 'state' => 'learned'],
            ],
            'bands' => [
                [
                    'number' => 1,
                    'key' => 'low',
                    'mode' => ['value' => 'LSHV', 'state' => 'learned'],
                    'frequency_hz' => ['value' => 150, 'state' => 'learned'],
                    'gain_db' => ['value' => 3.5, 'state' => 'learned'],
                    'on' => ['value' => true, 'state' => 'learned'],
                ],
            ],
        ];

        $show = $this->showWithBaseline($summary);

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 1]))
            ->assertOk()
            ->assertSee('Learned from console', false)
            ->assertSee('value="+3.5"', false)
            ->assertSee('value="150"', false)
            ->assertSee('value="LSHV"', false)
            ->assertDontSee('Placeholder EQ display only. Values shown are not from the X32.', false);
    }

    public function test_configuration_page_still_passes_after_bus_workspace_wiring(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Configuration Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Configuration Baseline');

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertOk()
            ->assertSee('X32 Configuration', false);
    }

    /**
     * @param  array<string, mixed>|null  $summaryOverride
     */
    private function showWithBaseline(?array $summaryOverride = null): Show
    {
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Monitors Show']);
        $device = $this->createX32Device($band);

        if ($summaryOverride !== null) {
            $snapshot = ConsoleLearningSnapshot::factory()->create([
                'band_id' => $band->id,
                'show_id' => $show->id,
                'integration_device_id' => $device->id,
                'requested_scene_number' => '01',
                'learning_status' => ConsoleLearningStatus::Saved,
                'learned_summary_json' => $summaryOverride,
            ]);

            ShowConsoleBaseline::factory()->create([
                'band_id' => $band->id,
                'show_id' => $show->id,
                'source_snapshot_id' => $snapshot->id,
                'baseline_name' => 'Monitors Baseline',
                'baseline_json' => $summaryOverride,
            ]);

            return $show;
        }

        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Monitors Baseline');

        return $show;
    }

    /**
     * @param  list<array<string, mixed>>  $extraBuses
     * @return array<string, mixed>
     */
    private function summaryWithNamedBus(int $number, string $name, array $extraBuses = []): array
    {
        $channels = [];

        for ($channelNumber = 1; $channelNumber <= 32; $channelNumber++) {
            $channels[] = ['number' => $channelNumber];
        }

        return [
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'configuration' => [
                'identity' => [],
                'channels' => $channels,
                'buses' => array_merge([
                    [
                        'number' => $number,
                        'name' => ['value' => $name, 'state' => 'learned'],
                        'fader' => ['value' => 0.75, 'state' => 'learned'],
                        'mute' => ['value' => false, 'state' => 'learned'],
                    ],
                ], $extraBuses),
                'warnings' => ['Routing table incomplete.'],
            ],
        ];
    }

    private function createX32Device(Band $band): IntegrationDevice
    {
        $device = IntegrationDevice::factory()->forBand($band)->create([
            'device_key' => 'foh-x32',
            'name' => 'FOH X32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'osc-main',
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '127.0.0.1',
            'port' => 10023,
            'enabled' => true,
        ]);

        return $device;
    }
}
