<?php

namespace Tests\Feature;

use App\Enums\ConsoleLearningStatus;
use App\Models\Band;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\X32InputChannelControlMap;
use App\Services\X32\X32MonitorSendControlMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleMonitorSendControlTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    #[Test]
    public function monitor_page_exposes_send_control_endpoint_when_live_control_available(): void
    {
        $show = $this->showWithBaseline($this->summaryWithSendLearned());

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 3]))
            ->assertOk()
            ->assertSee(route('shows.console.bus.sends.update', [$show, 3]), false)
            ->assertSee('data-monitors-send-control', false)
            ->assertSee('data-send-level-enabled="true"', false)
            ->assertSee('data-send-mute-enabled="true"', false);
    }

    #[Test]
    public function send_level_write_targets_selected_bus_path_and_confirms_read_back(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorSendControlMap::oscPath(1, 2, 'level');
        $fakeOsc->seedFloat($path, 0.5);

        $response = $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.sends.update', [$show, 2]), [
                'channel' => 1,
                'parameter' => 'level',
                'value' => 0.75,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'channel' => 1,
                'bus' => 2,
                'parameter' => 'level',
                'osc_path' => '/ch/01/mix/02/level',
            ]);

        $this->assertCount(1, $fakeOsc->writes());
        $this->assertSame('/ch/01/mix/02/level', $fakeOsc->writes()[0]['path']);
        $this->assertNotSame('/ch/01/mix/fader', $fakeOsc->writes()[0]['path']);
    }

    #[Test]
    public function send_mute_write_targets_send_on_path_with_inverted_semantics(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorSendControlMap::oscPath(6, 4, 'mute');
        $fakeOsc->seedInt($path, 1);

        $response = $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.sends.update', [$show, 4]), [
                'channel' => 6,
                'parameter' => 'mute',
                'value' => true,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'osc_path' => '/ch/06/mix/04/on',
                'requested_value' => true,
                'confirmed_value' => true,
            ]);

        $this->assertSame(0, $fakeOsc->writes()[0]['value']);
        $this->assertNotSame(
            X32InputChannelControlMap::oscPath('mute', 6),
            $fakeOsc->writes()[0]['path'],
        );
    }

    #[Test]
    public function failed_read_back_returns_safe_failure_response(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorSendControlMap::oscPath(1, 1, 'level');
        $fakeOsc->seedFloat($path, 0.5);
        $fakeOsc->queryFailPaths[] = $path;

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.sends.update', [$show, 1]), [
                'channel' => 1,
                'parameter' => 'level',
                'value' => 0.75,
            ])
            ->assertOk()
            ->assertJson([
                'success' => false,
                'error' => 'Monitor send write failed: Fake X32 OSC read-back failed for /ch/01/mix/01/level',
            ]);
    }

    #[Test]
    public function monitor_send_route_does_not_expose_pan_type_or_bus_master_writes(): void
    {
        $this->assertNull(X32MonitorSendControlMap::oscPath(1, 1, 'pan'));
        $this->assertNull(X32MonitorSendControlMap::oscPath(1, 1, 'type'));
        $this->assertNull(X32MonitorSendControlMap::oscPath(1, 1, 'pan_follow'));
        $this->assertNotContains('pan', X32MonitorSendControlMap::allowedParameters());
        $this->assertNotContains('type', X32MonitorSendControlMap::allowedParameters());
    }

    #[Test]
    public function group_mute_uses_monitor_send_on_path_for_each_group_member_on_selected_bus(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $paths = [
            1 => X32MonitorSendControlMap::oscPath(1, 3, 'mute'),
            2 => X32MonitorSendControlMap::oscPath(2, 3, 'mute'),
        ];

        foreach ($paths as $path) {
            $fakeOsc->seedInt($path, 1);
        }

        $user = $this->createDirectorUser();

        foreach ([1, 2] as $channel) {
            $this->actingAs($user)
                ->postJson(route('shows.console.bus.sends.update', [$show, 3]), [
                    'channel' => $channel,
                    'parameter' => 'mute',
                    'value' => true,
                ])
                ->assertOk()
                ->assertJson([
                    'success' => true,
                    'bus' => 3,
                    'osc_path' => $paths[$channel],
                ]);
        }

        $this->assertCount(2, $fakeOsc->writes());
        $this->assertSame('/ch/01/mix/03/on', $fakeOsc->writes()[0]['path']);
        $this->assertSame('/ch/02/mix/03/on', $fakeOsc->writes()[1]['path']);
        $this->assertSame(0, $fakeOsc->writes()[0]['value']);
        $this->assertSame(0, $fakeOsc->writes()[1]['value']);
    }

    #[Test]
    public function partial_group_mute_failure_is_reported_per_channel_without_fake_success(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $successPath = X32MonitorSendControlMap::oscPath(1, 3, 'mute');
        $failPath = X32MonitorSendControlMap::oscPath(2, 3, 'mute');
        $fakeOsc->seedInt($successPath, 1);
        $fakeOsc->seedInt($failPath, 1);
        $fakeOsc->queryFailPaths[] = $failPath;

        $user = $this->createDirectorUser();

        $first = $this->actingAs($user)
            ->postJson(route('shows.console.bus.sends.update', [$show, 3]), [
                'channel' => 1,
                'parameter' => 'mute',
                'value' => true,
            ])
            ->json();

        $second = $this->actingAs($user)
            ->postJson(route('shows.console.bus.sends.update', [$show, 3]), [
                'channel' => 2,
                'parameter' => 'mute',
                'value' => true,
            ])
            ->json();

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success']);
    }

    #[Test]
    public function group_fader_module_does_not_call_fetch_directly(): void
    {
        $contents = file_get_contents(resource_path('js/x32-monitors-group-control.js'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('x32-monitors-send-api', $contents);
        $this->assertStringContainsString('x32-monitors-group-trim', $contents);
        $this->assertStringNotContainsString('fetch(', $contents);
        $this->assertStringContainsString('data-group-fader-handle', $contents);
        $this->assertStringContainsString('groupBaselineAverageDb', $contents);
        $this->assertStringContainsString('groupFaderDisplayForKey', $contents);
        $this->assertStringContainsString('trimOffsetFromGroupFaderDb', $contents);
        $this->assertStringNotContainsString('applyGroupLevel', $contents);
    }

    #[Test]
    public function group_trim_module_exports_relative_offset_helpers(): void
    {
        $contents = file_get_contents(resource_path('js/x32-monitors-group-trim.js'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('applyGroupTrimLevels', $contents);
        $this->assertStringContainsString('averageBaselineDb', $contents);
        $this->assertStringContainsString('trimOffsetFromGroupFaderDb', $contents);
        $this->assertStringContainsString('clampSendLevelDb', $contents);
        $this->assertStringContainsString('GROUP_SEND_DB_MIN', $contents);
        $this->assertStringContainsString('GROUP_SEND_DB_MAX', $contents);
    }

    #[Test]
    public function send_api_module_owns_monitor_mute_fetch_and_active_mute_class(): void
    {
        $contents = file_get_contents(resource_path('js/x32-monitors-send-api.js'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('resolveMutedFromPayload', $contents);
        $this->assertStringContainsString('is-muted', $contents);
        $this->assertStringContainsString('confirmed_value', $contents);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryWithSendLearned(): array
    {
        return [
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'configuration' => [
                'channels' => [[
                    'number' => 1,
                    'name' => ['value' => 'Kick', 'state' => 'learned'],
                    'sends' => [
                        'buses' => [
                            '3' => [
                                'level' => [
                                    'value' => ['linear' => 0.75, 'value' => 0.0],
                                    'state' => 'learned',
                                    'source' => '/ch/01/mix/03/level',
                                ],
                                'on' => [
                                    'value' => true,
                                    'state' => 'learned',
                                    'source' => '/ch/01/mix/03/on',
                                ],
                            ],
                        ],
                    ],
                ]],
                'buses' => [[
                    'number' => 3,
                    'name' => ['value' => 'Ed IEM', 'state' => 'learned'],
                ]],
            ],
        ];
    }

    /**
     * @return array{0: Show, 1: FakeX32OscConsoleClient}
     */
    private function showWithLiveBaseline(): array
    {
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = IntegrationDevice::factory()->forBand($band)->create([
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
            'configuration' => ['runtime_mode' => 'live'],
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '127.0.0.1',
            'port' => 10023,
            'enabled' => true,
        ]);

        $summary = $this->summaryWithSendLearned();
        $snapshot = ConsoleLearningSnapshot::factory()->create([
            'band_id' => $band->id,
            'show_id' => $show->id,
            'integration_device_id' => $device->id,
            'learning_status' => ConsoleLearningStatus::Saved,
            'learned_summary_json' => $summary,
        ]);

        ShowConsoleBaseline::factory()->create([
            'band_id' => $band->id,
            'show_id' => $show->id,
            'source_snapshot_id' => $snapshot->id,
            'baseline_json' => $summary,
        ]);

        $fakeOsc = app(FakeX32OscConsoleClient::class);
        $fakeOsc->queryFailPaths = [];
        $fakeOsc->shouldFail = false;

        return [$show, $fakeOsc];
    }

    private function showWithBaseline(array $summary): Show
    {
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = IntegrationDevice::factory()->forBand($band)->create([
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
            'configuration' => ['runtime_mode' => 'live'],
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '127.0.0.1',
            'port' => 10023,
            'enabled' => true,
        ]);

        $snapshot = ConsoleLearningSnapshot::factory()->create([
            'band_id' => $band->id,
            'show_id' => $show->id,
            'integration_device_id' => $device->id,
            'learning_status' => ConsoleLearningStatus::Saved,
            'learned_summary_json' => $summary,
        ]);

        ShowConsoleBaseline::factory()->create([
            'band_id' => $band->id,
            'show_id' => $show->id,
            'source_snapshot_id' => $snapshot->id,
            'baseline_json' => $summary,
        ]);

        return $show;
    }
}
