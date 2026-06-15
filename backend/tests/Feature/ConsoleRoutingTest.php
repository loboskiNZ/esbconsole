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

class ConsoleRoutingTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_routing_page_redirects_when_no_baseline(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'No Baseline']);
        $this->createX32Device($band);

        $this->actingAs($user)
            ->get(route('shows.console.routing', $show))
            ->assertRedirect(route('shows.console.learn', $show));
    }

    public function test_routing_workspace_shell_renders(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Routing Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Routing Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console.routing', $show));

        $response->assertOk()
            ->assertSee('vx32-routing-workspace', false)
            ->assertSee('vx32-routing-workspace__header', false)
            ->assertSee('ESB Console')
            ->assertSee('Audio Routing')
            ->assertSee('vx32-routing-workspace__routing-state', false)
            ->assertSee(route('shows.console', $show, false));
    }

    public function test_routing_flow_row_renders_complete_map(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Flow Row Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Flow Row Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console.routing', $show));

        $response->assertOk()
            ->assertSee('Routing Flow')
            ->assertSee('Sources', false)
            ->assertSee('Console Processing', false)
            ->assertSee('Destinations', false)
            ->assertSee('vx32-routing-flow__map', false)
            ->assertSee('vx32-routing-flow__svg', false)
            ->assertSee('vx32-routing-flow__source-row', false)
            ->assertSee('vx32-routing-flow__destination-row', false)
            ->assertSee('Stagebox A')
            ->assertSee('Stagebox B')
            ->assertSee('Ableton')
            ->assertSee('Console Channels')
            ->assertSee('CH01–CH32')
            ->assertSee('FOH')
            ->assertSee('IEMs')
            ->assertSee('Main L')
            ->assertSee('Main R')
            ->assertSee('Suggested')
            ->assertSee('Configure')
            ->assertSee('Not available yet', false)
            ->assertDontSee('Source Row', false);
    }

    public function test_configuration_detail_row_renders_three_column_workspace(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Detail Row Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Detail Row Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console.routing', $show));

        $response->assertOk()
            ->assertSee('vx32-routing-detail__grid', false)
            ->assertSee('vx32-routing-detail__console-strip', false)
            ->assertSee('Learned from FOH X32')
            ->assertSee('Input Sources')
            ->assertSee('Channel Allocation Overview')
            ->assertSee('Assigned below')
            ->assertSee('Returns assigned below')
            ->assertSee('Expected')
            ->assertSee('Outputs')
            ->assertSee('Presets')
            ->assertSee('Learn From Console')
            ->assertSee('Ableton')
            ->assertDontSee('Ableton Live Returns')
            ->assertSee('IEM Mixes')
            ->assertSee('Spare outputs')
            ->assertSee('View All Outputs');
    }

    public function test_routing_bottom_row_renders_workflow_and_advanced_entry(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Bottom Row Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Bottom Row Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console.routing', $show));

        $response->assertOk()
            ->assertSee('vx32-routing-bottom__grid', false)
            ->assertSee('Configuration Actions')
            ->assertSee('Advanced X32 Routing')
            ->assertSee('Learn From Console')
            ->assertSee('Edit Configuration')
            ->assertSee('Preview Changes')
            ->assertSee('Sync To Console')
            ->assertSee('Save Configuration')
            ->assertSee('Not available yet')
            ->assertSee('Coming later')
            ->assertSee('View Advanced Routing')
            ->assertSee('AES50A')
            ->assertSee('P16 / Ultranet')
            ->assertSee(route('shows.console.learn', $show, false));
    }

    public function test_console_overview_links_to_routing_tab(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Link Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Link Baseline');

        $this->actingAs($user)
            ->get(route('shows.console', $show))
            ->assertOk()
            ->assertSee(route('shows.console.routing', $show, false));
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
