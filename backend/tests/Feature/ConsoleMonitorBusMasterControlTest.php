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
use App\Services\X32\X32FaderScale;
use App\Services\X32\X32MonitorBusMasterControlMap;
use App\Services\X32\X32MonitorSendControlMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleMonitorBusMasterControlTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    #[Test]
    public function monitor_page_exposes_bus_master_control_endpoint_when_live_control_available(): void
    {
        $show = $this->showWithBaseline($this->summaryWithLearnedBusMaster());

        $this->actingAs($this->createDirectorUser())
            ->get(route('shows.console.bus.layout', [$show, 3]))
            ->assertOk()
            ->assertSee(route('shows.console.bus.master.update', [$show, 3]), false)
            ->assertSee('data-bus-master-control-url', false)
            ->assertSee('data-bus-master-live-control', false)
            ->assertSee('data-bus-master-mute', false)
            ->assertSee('data-bus-master-level-enabled="true"', false)
            ->assertSee('data-bus-master-mute-enabled="true"', false);
    }

    #[Test]
    public function bus_master_fader_write_targets_selected_bus_mix_fader_path(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorBusMasterControlMap::oscPath(3, X32MonitorBusMasterControlMap::PARAMETER_LEVEL);
        $fakeOsc->seedFloat($path, 0.5);

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.master.update', [$show, 3]), [
                'parameter' => 'level',
                'value' => 0.75,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'bus' => 3,
                'parameter' => 'level',
                'osc_path' => '/bus/03/mix/fader',
            ]);

        $this->assertSame('/bus/03/mix/fader', $fakeOsc->writes()[0]['path']);
        $this->assertEqualsWithDelta(
            X32FaderScale::quantizeLinear(0.75),
            $fakeOsc->writes()[0]['value'],
            0.0001,
        );
    }

    #[Test]
    public function bus_master_mute_write_targets_selected_bus_mix_on_with_inverted_ui_semantics(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorBusMasterControlMap::oscPath(3, X32MonitorBusMasterControlMap::PARAMETER_MUTE);
        $fakeOsc->seedInt($path, 1);

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.master.update', [$show, 3]), [
                'parameter' => 'mute',
                'value' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'osc_path' => '/bus/03/mix/on',
                'encoded_osc_value' => 0,
                'confirmed_value' => 0,
                'display_value' => 'Muted',
            ]);
    }

    #[Test]
    public function failed_read_back_returns_safe_failure_response(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorBusMasterControlMap::oscPath(3, X32MonitorBusMasterControlMap::PARAMETER_LEVEL);
        $fakeOsc->seedFloat($path, 0.5);
        $fakeOsc->queryFailPaths[] = $path;

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.master.update', [$show, 3]), [
                'parameter' => 'level',
                'value' => 0.75,
            ])
            ->assertOk()
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('error', 'Monitor bus master write failed: Fake X32 OSC read-back failed for /bus/03/mix/fader');
    }

    #[Test]
    public function confirmed_read_back_updates_display_value_in_response(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $path = X32MonitorBusMasterControlMap::oscPath(3, X32MonitorBusMasterControlMap::PARAMETER_LEVEL);
        $fakeOsc->seedFloat($path, 0.5);

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.master.update', [$show, 3]), [
                'parameter' => 'level',
                'value' => 0.75,
            ])
            ->assertOk()
            ->assertJsonPath('display_value', X32MonitorBusMasterControlMap::levelDisplayFromLinear(0.75));
    }

    #[Test]
    public function writes_are_scoped_to_selected_bus_from_route_not_request_body(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveBaseline();
        $busThreePath = X32MonitorBusMasterControlMap::oscPath(3, X32MonitorBusMasterControlMap::PARAMETER_MUTE);
        $busFivePath = X32MonitorBusMasterControlMap::oscPath(5, X32MonitorBusMasterControlMap::PARAMETER_MUTE);
        $fakeOsc->seedInt($busThreePath, 1);
        $fakeOsc->seedInt($busFivePath, 1);

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.master.update', [$show, 3]), [
                'parameter' => 'mute',
                'value' => true,
                'bus' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('osc_path', '/bus/03/mix/on');

        $this->assertSame('/bus/03/mix/on', $fakeOsc->writes()[0]['path']);
    }

    #[Test]
    public function bus_master_route_does_not_expose_unrelated_write_scopes(): void
    {
        $this->assertNull(X32MonitorBusMasterControlMap::oscPath(1, 'f'));
        $this->assertNull(X32MonitorBusMasterControlMap::oscPath(1, 'on'));
        $this->assertNotNull(X32MonitorSendControlMap::oscPath(1, 1, 'level'));
        $this->assertNotContains('f', X32MonitorBusMasterControlMap::allowedParameters());
        $this->assertStringStartsWith('/bus/', (string) X32MonitorBusMasterControlMap::oscPath(1, X32MonitorBusMasterControlMap::PARAMETER_LEVEL));
        $this->assertStringContainsString('/mix/fader', (string) X32MonitorBusMasterControlMap::oscPath(1, X32MonitorBusMasterControlMap::PARAMETER_LEVEL));
    }

    #[Test]
    public function bus_master_writes_are_blocked_when_runtime_mode_is_not_live(): void
    {
        [$show] = $this->showWithLiveBaseline(runtimeMode: 'dry_run');

        $this->actingAs($this->createDirectorUser())
            ->postJson(route('shows.console.bus.master.update', [$show, 3]), [
                'parameter' => 'level',
                'value' => 0.75,
            ])
            ->assertOk()
            ->assertJsonPath('error', 'Live X32 control is not enabled for this console device.');
    }

    #[Test]
    public function bus_master_control_js_modules_use_confirmed_values(): void
    {
        $api = file_get_contents(resource_path('js/x32-monitors-bus-master-api.js'));
        $control = file_get_contents(resource_path('js/x32-monitors-bus-master-control.js'));

        $this->assertIsString($api);
        $this->assertIsString($control);
        $this->assertStringContainsString('writeMonitorBusMaster', $api);
        $this->assertStringContainsString('display_value', $api);
        $this->assertStringContainsString('confirmed_value', $control);
        $this->assertStringNotContainsString('/ch/', $control);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryWithLearnedBusMaster(): array
    {
        return [
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'configuration' => [
                'channels' => [],
                'buses' => [[
                    'number' => 3,
                    'name' => ['value' => 'Ed IEM', 'state' => 'learned'],
                    'fader' => ['value' => 0.75, 'state' => 'learned'],
                    'mute' => ['value' => false, 'state' => 'learned'],
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

        $summary = $this->summaryWithLearnedBusMaster();
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
