<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Services\Console\ShowConsoleBaselineService;
use App\Services\Console\X32ConsoleLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleConfigurationTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_configuration_page_redirects_when_no_baseline(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'No Baseline']);
        $this->createX32Device($band);

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertRedirect(route('shows.console.learn', $show));
    }

    public function test_configuration_workspace_shell_renders(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Configuration Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Configuration Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console.configuration', $show));

        $response->assertOk()
            ->assertSee('vx32-configuration-workspace', false)
            ->assertSee('vx32-routing-workspace__header', false)
            ->assertSee('vx32-routing-detail__panel', false)
            ->assertSee('X32 Configuration')
            ->assertSee('Learned from FOH X32 · Scene 01')
            ->assertSee('Configuration Status')
            ->assertSee('Learn Again')
            ->assertSee('This page shows the learned configuration of your X32 console')
            ->assertSee('View audio routing →')
            ->assertSee(route('shows.console.routing', $show, false))
            ->assertDontSee('Channels (1–32)')
            ->assertDontSee('Buses &amp; Monitors', false)
            ->assertSee('Firmware')
            ->assertSee('Not captured yet');
    }

    public function test_configuration_tab_appears_on_overview_and_routing_pages(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Tab Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Tab Baseline');

        $configurationUrl = route('shows.console.configuration', $show, false);

        $this->actingAs($user)
            ->get(route('shows.console', $show))
            ->assertOk()
            ->assertSee('CONFIGURATION', false)
            ->assertSee($configurationUrl, false);

        $this->actingAs($user)
            ->get(route('shows.console.routing', $show))
            ->assertOk()
            ->assertSee('CONFIGURATION', false)
            ->assertSee($configurationUrl, false);

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertOk()
            ->assertSee('vx32-tabs__btn is-active', false)
            ->assertSee('CONFIGURATION', false);
    }

    public function test_configuration_page_shows_partial_status_when_key_fields_are_missing(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $baseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Partial Baseline');

        $summary = $baseline->baseline_json;
        $summary['configuration']['warnings'] = [];
        $baseline->update(['baseline_json' => $summary]);

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertOk()
            ->assertSee('Partial', false)
            ->assertSee('vx32-routing-workspace__routing-state--suggested', false)
            ->assertSee('Not captured yet')
            ->assertSee('Preview data');
    }

    public function test_configuration_page_shows_needs_attention_for_fixture_warnings(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Fixture Baseline');

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertOk()
            ->assertSee('Needs attention', false)
            ->assertSee('vx32-routing-workspace__routing-state--not-learned', false);
    }

    public function test_configuration_page_shows_not_learned_status_for_legacy_baseline(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $baseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Legacy Baseline');

        $summary = $baseline->baseline_json;
        unset($summary['configuration']);
        $baseline->update(['baseline_json' => $summary]);

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertOk()
            ->assertSee('Not learned', false)
            ->assertSee('vx32-routing-workspace__routing-state--not-learned', false)
            ->assertSee('Learned from FOH X32 · Scene 01');
    }

    public function test_configuration_page_shows_needs_attention_status_when_warnings_exist(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $baseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Warning Baseline');

        $summary = $baseline->baseline_json;
        $summary['configuration']['warnings'] = ['Configuration identity globals require live OSC transport.'];
        $summary['configuration']['globals']['sample_rate'] = ['value' => '48K', 'state' => 'learned'];
        $summary['configuration']['globals']['clock_source'] = ['value' => 'INT', 'state' => 'learned'];
        $baseline->update(['baseline_json' => $summary]);

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertOk()
            ->assertSee('Needs attention', false)
            ->assertSee('vx32-routing-workspace__routing-state--not-learned', false);
    }

    public function test_configuration_page_shows_identity_cards_with_known_and_unknown_fields(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $baseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Identity Baseline');

        $summary = $baseline->baseline_json;
        $summary['configuration']['warnings'] = [];
        $baseline->update(['baseline_json' => $summary]);

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertOk()
            ->assertSee('Console Name')
            ->assertSee('FOH X32')
            ->assertSee('Device Key')
            ->assertSee('Firmware')
            ->assertSee('Not captured yet')
            ->assertSee('Scene Number')
            ->assertSee('SAMPLE RATE / CLOCK')
            ->assertSee('LEARN STATUS')
            ->assertSee('Not yet captured', false)
            ->assertSee('FX inventory not captured yet')
            ->assertSee('vx32-routing-detail__status-tile', false);
    }

    public function test_configuration_page_shows_firmware_when_learned(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $baseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Firmware Baseline');

        $summary = $baseline->baseline_json;
        $summary['configuration']['identity']['firmware'] = ['value' => '4.06', 'state' => 'learned'];
        $summary['configuration']['globals']['firmware'] = ['value' => '4.06', 'state' => 'learned'];
        $baseline->update(['baseline_json' => $summary]);

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertOk()
            ->assertSee('Firmware')
            ->assertSee('4.06');
    }

    public function test_configuration_page_shows_complete_status_for_healthy_audit_structure(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $baseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Complete Baseline');

        $summary = $baseline->baseline_json;
        $summary['configuration']['warnings'] = [];
        $summary['configuration']['identity']['firmware'] = ['value' => '4.06', 'state' => 'learned'];
        $summary['configuration']['globals']['sample_rate'] = ['value' => '48K', 'state' => 'learned'];
        $summary['configuration']['globals']['clock_source'] = ['value' => 'INT', 'state' => 'learned'];
        $summary['configuration']['globals']['firmware'] = ['value' => '4.06', 'state' => 'learned'];
        $summary['configuration']['fx'] = ['learned' => true, 'slots' => []];
        $summary['configuration']['dcas'] = [[
            'number' => 1,
            'membership' => ['value' => [1], 'state' => 'learned'],
        ]];
        $summary['configuration']['matrices'] = [[
            'number' => 1,
            'sources' => ['value' => [], 'state' => 'learned'],
        ]];
        $baseline->update(['baseline_json' => $summary]);

        $this->actingAs($user)
            ->get(route('shows.console.configuration', $show))
            ->assertOk()
            ->assertSee('Complete', false)
            ->assertSee('vx32-routing-workspace__routing-state--learned', false)
            ->assertDontSee('Not yet captured', false);
    }

    public function test_configuration_page_has_no_sync_or_configuration_edit_controls(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Read Only Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console.configuration', $show));

        $response->assertOk()
            ->assertDontSee('Sync to console')
            ->assertDontSee('Configure')
            ->assertDontSee('shows.console.parameters.update', false)
            ->assertDontSee('shows.console.controls.update', false)
            ->assertDontSee('name="parameter"', false);
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
