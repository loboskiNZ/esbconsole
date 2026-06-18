<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Services\Console\ShowConsoleBaselineService;
use App\Services\Console\X32ConsoleLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleEffectsTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_effects_page_redirects_when_no_baseline(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'No Baseline']);
        $this->createX32Device($band);

        $this->actingAs($user)
            ->get(route('shows.console.effects', $show))
            ->assertRedirect(route('shows.console.learn', $show));
    }

    public function test_effects_workspace_scaffold_renders(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Effects Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console.effects', $show));

        $response->assertOk()
            ->assertSee('vx32-effects-workspace', false)
            ->assertSee('vx32-routing-workspace__title', false)
            ->assertSee('Effects', false)
            ->assertSee('Effect Packages', false)
            ->assertSee('Effect Details', false)
            ->assertSee('Selected Package Deployment Plan', false)
            ->assertSee('vx32-effects-workspace__new-btn', false);
    }

    public function test_effects_tab_is_active_in_console_navigation(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Tab Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Effects Tab Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console.effects', $show));

        $response->assertOk()
            ->assertSee(route('shows.console.effects', $show, false), false)
            ->assertSee('EFFECTS', false)
            ->assertSee('vx32-tabs__btn is-active', false);
    }

    public function test_effects_layout_uses_twenty_five_fifty_twenty_five_column_structure(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Layout Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Effects Layout Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console.effects', $show));

        $response->assertOk()
            ->assertSee('vx32-effects-workspace__grid', false)
            ->assertSee('vx32-effects-workspace__col--packages', false)
            ->assertSee('vx32-effects-workspace__col--details', false)
            ->assertSee('vx32-effects-workspace__col--summary', false);
    }

    public function test_no_effects_live_control_routes_are_added(): void
    {
        $allowedScaffoldUris = [
            'shows/{show}/console/effects',
            'shows/{show}/console/effects/newfxpackage',
            'shows/{show}/console/effects/packages/{package}/edit',
            'shows/{show}/console/effects/packages/{package}',
            'shows/{show}/console/effects/packages/{package}/add-effect',
            'shows/{show}/console/effects/package-items/{item}/edit',
            'shows/{show}/console/effects/package-items/{item}',
            'shows/{show}/console/effects/parameters/{parameter}',
            'shows/{show}/console/effects/package-items/{item}/slot',
            'shows/{show}/console/effects/package-items/{item}/deploy',
            'shows/{show}/console/effects/package-items/{item}/routing-plan',
        ];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (in_array($uri, $allowedScaffoldUris, true)) {
                if ($uri === 'shows/{show}/console/effects') {
                    $this->assertContains('GET', $route->methods(), "Scaffold effects index route must be GET only: {$uri}");
                }

                if ($uri === 'shows/{show}/console/effects/parameters/{parameter}') {
                    $this->assertSame(['PATCH'], $route->methods(), "Package parameter route must be PATCH only: {$uri}");
                }

                if ($uri === 'shows/{show}/console/effects/package-items/{item}/slot') {
                    $this->assertSame(['POST'], $route->methods(), "Package item slot route must be POST only: {$uri}");
                }

                if ($uri === 'shows/{show}/console/effects/package-items/{item}/deploy') {
                    $this->assertSame(['POST'], $route->methods(), "Package item deploy route must be POST only: {$uri}");
                }

                if ($uri === 'shows/{show}/console/effects/package-items/{item}/routing-plan') {
                    $this->assertSame(['POST'], $route->methods(), "Routing plan route must be POST only: {$uri}");
                }

                continue;
            }

            $this->assertDoesNotMatchRegularExpression('#(^|/)effects(/|$)#', $uri, "Unexpected effects route: {$uri}");
        }
    }

    public function test_no_effect_osc_write_services_are_added_for_effects_scaffold(): void
    {
        $this->assertFalse(class_exists(\App\Services\Console\ShowConsoleEffectControlService::class));
        $this->assertFalse(class_exists(\App\Services\X32\X32EffectOscWriter::class));
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
