<?php

namespace Tests\Feature;

use App\Enums\EffectPackageDeploymentPlanStatus;
use App\Enums\X32SlotGroup;
use App\Models\Band;
use App\Models\EffectPackage;
use App\Models\EffectPackageTypeOption;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\X32Effect;
use App\Services\Console\ShowConsoleBaselineService;
use App\Services\Console\X32ConsoleLearningService;
use App\Services\Effects\CreateEffectPackageService;
use App\Services\Effects\EffectPackageDeploymentPlanPreviewService;
use Database\Seeders\EffectLibraryReferenceSeeder;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleEffectsDeploymentPlanPreviewTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_right_column_renders_selected_package_deployment_plan(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT'], 'Reggae Dub');

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('Selected Package Deployment Plan', false)
            ->assertSee('vx32-effects-deployment-plan__table', false)
            ->assertSee('REGGAE DUB', false)
            ->assertSee('Song Package', false);
    }

    public function test_fx_slot_rows_render_for_fx1_through_fx8(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->getContent();

        foreach (range(1, 8) as $slot) {
            $this->assertStringContainsString('>FX'.$slot.'<', $content);
        }
    }

    public function test_allocated_effects_appear_in_correct_slot_rows(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 3]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('vx32-effects-deployment-plan__row--ready', false)
            ->assertSee('Vocal Plate', false)
            ->assertSee('PLAT', false);
    }

    public function test_unallocated_effects_appear_clearly(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('Unallocated Effects', false)
            ->assertSee('Vocal Plate', false)
            ->assertSee('Vocal Delay', false);
    }

    public function test_permanent_slot_reservations_appear_for_song_package(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $permanent = $this->createPackageWithEffects(['GEQ'], 'FOH Main', EffectPackageTypeOption::SLUG_PERMANENT);
        $song = $this->createPackageWithEffects(['PLAT'], 'Reggae Dub');
        $permanent->effectPackageItems->firstOrFail()->update(['preferred_slot_number' => 5]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $song->id]))
            ->assertOk()
            ->assertSee('Permanent Slot Reservations', false)
            ->assertSee('FX5 reserved by FOH MAIN', false);
    }

    public function test_ready_status_when_no_conflicts(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $package->effectPackageItems->firstOrFail()->update(['preferred_slot_number' => 3]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('vx32-effects-deployment-plan__overall-status--ready', false)
            ->assertSee('READY', false);

        $preview = app(EffectPackageDeploymentPlanPreviewService::class)->preview($package->fresh('effectPackageItems.x32Effect'));
        $this->assertSame(EffectPackageDeploymentPlanStatus::Ready, $preview['status']);
    }

    public function test_ready_with_warnings_when_unallocated_effects_exist(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);
        $package->effectPackageItems->firstWhere(fn ($item) => $item->x32Effect?->effect_code === 'PLAT')
            ->update(['preferred_slot_number' => 3]);

        $preview = app(EffectPackageDeploymentPlanPreviewService::class)->preview($package->fresh('effectPackageItems.x32Effect'));

        $this->assertSame(EffectPackageDeploymentPlanStatus::ReadyWithWarnings, $preview['status']);
    }

    public function test_blocked_status_when_conflicts_exist(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);
        $items = $package->effectPackageItems;
        $items->firstWhere(fn ($item) => $item->x32Effect?->effect_code === 'PLAT')->update(['preferred_slot_number' => 3]);
        $items->firstWhere(fn ($item) => $item->x32Effect?->effect_code === 'DLY')->update(['preferred_slot_number' => 3]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('vx32-effects-deployment-plan__overall-status--blocked', false)
            ->assertSee('BLOCKED', false)
            ->assertSee('Conflicts', false);

        $preview = app(EffectPackageDeploymentPlanPreviewService::class)->preview($package->fresh('effectPackageItems.x32Effect'));
        $this->assertSame(EffectPackageDeploymentPlanStatus::Blocked, $preview['status']);
    }

    public function test_no_effects_live_control_routes_are_added_by_deployment_preview_work(): void
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
                continue;
            }

            $this->assertDoesNotMatchRegularExpression('#(^|/)effects(/|$)#', $uri, "Unexpected effects route: {$uri}");
        }
    }

    /**
     * @param  list<string>  $effectCodes
     */
    private function createPackageWithEffects(
        array $effectCodes,
        string $name = 'Deployment Preview Pack',
        string $typeSlug = EffectPackageTypeOption::SLUG_SONG_PACKAGE,
    ): EffectPackage {
        $packageType = EffectPackageTypeOption::query()->where('slug', $typeSlug)->firstOrFail();
        $effectIds = collect($effectCodes)
            ->map(fn (string $code) => X32Effect::query()
                ->where('effect_code', $code)
                ->where('x32_slot_group', in_array($code, ['GEQ', 'LIM'], true) ? X32SlotGroup::Fx5To8 : X32SlotGroup::Fx1To4)
                ->firstOrFail()
                ->id)
            ->all();

        return app(CreateEffectPackageService::class)->create([
            'name' => $name,
            'description' => null,
            'effect_package_type_id' => $packageType->id,
            'effect_ids' => $effectIds,
        ]);
    }

    /**
     * @return array{0: \App\Models\User, 1: Show}
     */
    private function createShowWithBaseline(): array
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Deployment Preview Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Deployment Preview Baseline');

        return [$user, $show];
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
