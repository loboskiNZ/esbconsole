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
use App\Services\X32\X32BusEqOscDecoder;
use App\Services\X32\X32MonitorBusEqControlMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleMonitorBusEqControlTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    #[Test]
    public function monitor_page_exposes_eq_control_endpoint_when_live_control_available(): void
    {
        $show = $this->showWithBaseline($this->summaryWithLearnedBusEq());

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 3]))
            ->assertOk()
            ->assertSee(route('shows.console.bus.eq.update', [$show, 3]), false)
            ->assertSee('data-monitors-eq-control', false)
            ->assertSee('data-eq-control-url', false)
            ->assertSee('data-eq-master-toggle', false);
    }

    #[Test]
    public function eq_on_write_targets_selected_bus_path_and_confirms_read_back(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorBusEqControlMap::oscPath(3, X32MonitorBusEqControlMap::PARAMETER_ON);
        $fakeOsc->seedInt($path, 0);

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.eq.update', [$show, 3]), [
                'parameter' => 'on',
                'value' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'bus' => 3,
                'parameter' => 'on',
                'osc_path' => '/bus/03/eq/on',
                'display_value' => 'ON',
            ]);

        $this->assertSame('/bus/03/eq/on', $fakeOsc->writes()[0]['path']);
    }

    #[Test]
    public function band_type_write_targets_selected_bus_band_path(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorBusEqControlMap::oscPath(3, X32MonitorBusEqControlMap::PARAMETER_TYPE, 2);
        $fakeOsc->seedInt($path, 1);

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.eq.update', [$show, 3]), [
                'band' => 2,
                'parameter' => 'type',
                'value' => 'PEQ',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'bus' => 3,
                'band' => 2,
                'parameter' => 'type',
                'osc_path' => '/bus/03/eq/2/type',
                'display_value' => 'PEQ',
            ]);
    }

    #[Test]
    public function frequency_gain_and_q_writes_target_selected_bus_band_paths(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $frequencyPath = X32MonitorBusEqControlMap::oscPath(3, X32MonitorBusEqControlMap::PARAMETER_FREQUENCY, 1);
        $gainPath = X32MonitorBusEqControlMap::oscPath(3, X32MonitorBusEqControlMap::PARAMETER_GAIN, 4);
        $qPath = X32MonitorBusEqControlMap::oscPath(3, X32MonitorBusEqControlMap::PARAMETER_Q, 4);
        $fakeOsc->seedFloat($frequencyPath, 0.1);
        $fakeOsc->seedFloat($gainPath, 0.0);
        $fakeOsc->seedFloat($qPath, 0.2);

        $user = $this->createDirectorUser();

        $this->actingAs($user)
            ->postJson(route('shows.console.bus.eq.update', [$show, 3]), [
                'band' => 1,
                'parameter' => 'f',
                'value' => 500.0,
            ])
            ->assertOk()
            ->assertJsonPath('osc_path', '/bus/03/eq/1/f');

        $this->actingAs($user)
            ->postJson(route('shows.console.bus.eq.update', [$show, 3]), [
                'band' => 4,
                'parameter' => 'g',
                'value' => -2.5,
            ])
            ->assertOk()
            ->assertJsonPath('osc_path', '/bus/03/eq/4/g');

        $this->actingAs($user)
            ->postJson(route('shows.console.bus.eq.update', [$show, 3]), [
                'band' => 4,
                'parameter' => 'q',
                'value' => 2.5,
            ])
            ->assertOk()
            ->assertJsonPath('osc_path', '/bus/03/eq/4/q');

        $this->assertSame('/bus/03/eq/1/f', $fakeOsc->writes()[0]['path']);
        $this->assertSame('/bus/03/eq/4/g', $fakeOsc->writes()[1]['path']);
        $this->assertSame('/bus/03/eq/4/q', $fakeOsc->writes()[2]['path']);
    }

    #[Test]
    public function failed_read_back_returns_safe_failure_response(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorBusEqControlMap::oscPath(3, X32MonitorBusEqControlMap::PARAMETER_GAIN, 1);
        $fakeOsc->seedFloat($path, 0.0);
        $fakeOsc->queryFailPaths[] = $path;

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.eq.update', [$show, 3]), [
                'band' => 1,
                'parameter' => 'g',
                'value' => 3.0,
            ])
            ->assertOk()
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('error', 'Monitor bus EQ write failed: Fake X32 OSC read-back failed for /bus/03/eq/1/g');
    }

    #[Test]
    public function confirmed_read_back_updates_display_value_in_response(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorBusEqControlMap::oscPath(3, X32MonitorBusEqControlMap::PARAMETER_GAIN, 2);
        $encoded = X32BusEqOscDecoder::encodeGainDb(4.0);
        $fakeOsc->seedFloat($path, 0.0);

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.eq.update', [$show, 3]), [
                'band' => 2,
                'parameter' => 'g',
                'value' => 4.0,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'display_value' => '+4.0',
            ])
            ->assertJsonPath('confirmed_value', $encoded);
    }

    #[Test]
    public function writes_are_scoped_to_selected_bus_from_route_not_request_body(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $busThreePath = X32MonitorBusEqControlMap::oscPath(3, X32MonitorBusEqControlMap::PARAMETER_ON);
        $busFivePath = X32MonitorBusEqControlMap::oscPath(5, X32MonitorBusEqControlMap::PARAMETER_ON);
        $fakeOsc->seedInt($busThreePath, 0);
        $fakeOsc->seedInt($busFivePath, 0);

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.eq.update', [$show, 3]), [
                'parameter' => 'on',
                'value' => true,
                'bus' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('osc_path', '/bus/03/eq/on');

        $this->assertSame('/bus/03/eq/on', $fakeOsc->writes()[0]['path']);
        $this->assertNotSame('/bus/05/eq/on', $fakeOsc->writes()[0]['path']);
    }

    #[Test]
    public function eq_route_does_not_expose_send_channel_or_bus_master_fader_writes(): void
    {
        $this->assertNull(X32MonitorBusEqControlMap::oscPath(1, 'level', 1));
        $this->assertNull(X32MonitorBusEqControlMap::oscPath(1, 'mute', 1));
        $this->assertNotContains('level', X32MonitorBusEqControlMap::allowedParameters());
        $this->assertNotContains('mute', X32MonitorBusEqControlMap::allowedParameters());
        $this->assertStringStartsWith('/bus/', (string) X32MonitorBusEqControlMap::oscPath(1, X32MonitorBusEqControlMap::PARAMETER_GAIN, 1));
    }

    #[Test]
    public function eq_writes_are_blocked_when_runtime_mode_is_not_live(): void
    {
        [$show] = $this->showWithLiveBaseline(runtimeMode: 'dry_run');

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.eq.update', [$show, 3]), [
                'parameter' => 'on',
                'value' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('error', 'Live X32 control is not enabled for this console device.');
    }

    #[Test]
    public function eq_api_module_uses_confirmed_display_values(): void
    {
        $contents = file_get_contents(resource_path('js/x32-monitors-eq-api.js'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('writeMonitorBusEq', $contents);
        $this->assertStringContainsString('display_value', $contents);
        $this->assertStringContainsString('commitEqBandParameter', $contents);
    }

    #[Test]
    public function bus_eq_workspace_wires_live_commits_without_channel_eq_paths(): void
    {
        $contents = file_get_contents(resource_path('js/x32-bus-eq.js'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('x32-monitors-eq-api', $contents);
        $this->assertStringContainsString('commitEqBandParameter', $contents);
        $this->assertStringNotContainsString('/ch/', $contents);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryWithLearnedBusEq(): array
    {
        return [
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'configuration' => [
                'channels' => [],
                'buses' => [[
                    'number' => 3,
                    'name' => ['value' => 'Ed IEM', 'state' => 'learned'],
                    'eq' => [
                        'on' => ['value' => true, 'state' => 'learned'],
                        'bands' => [[
                            'number' => 1,
                            'mode' => ['value' => 'LSHV', 'state' => 'learned'],
                            'frequency_hz' => ['value' => 79.6, 'state' => 'learned'],
                            'gain_db' => ['value' => 0.0, 'state' => 'learned'],
                            'q' => ['value' => 2.0, 'state' => 'learned'],
                        ]],
                    ],
                ]],
            ],
        ];
    }

    /**
     * @return array{0: Show, 1: FakeX32OscConsoleClient}
     */
    private function showWithLiveBaseline(string $runtimeMode = 'live'): array
    {
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = IntegrationDevice::factory()->forBand($band)->create([
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
            'configuration' => ['runtime_mode' => $runtimeMode],
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '127.0.0.1',
            'port' => 10023,
            'enabled' => true,
        ]);

        $summary = $this->summaryWithLearnedBusEq();
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
