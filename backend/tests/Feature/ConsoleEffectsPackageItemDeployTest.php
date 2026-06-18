<?php

namespace Tests\Feature;

use App\Enums\X32SlotGroup;
use App\Models\Band;
use App\Models\ConsoleLearningSnapshot;
use App\Models\EffectPackage;
use App\Models\EffectPackageTypeOption;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Models\X32Effect;
use App\Services\Effects\CreateEffectPackageService;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\X32OscAddressMap;
use Database\Seeders\EffectLibraryReferenceSeeder;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleEffectsPackageItemDeployTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_deploy_button_exists_on_allocated_effects(): void
    {
        [$user, $show] = $this->showWithLiveBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 3]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('Deploy Effect', false)
            ->assertSee('data-effect-deploy', false)
            ->assertSee('Ready to deploy', false);
    }

    public function test_deploy_button_disabled_when_no_slot(): void
    {
        [$user, $show] = $this->showWithLiveBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Not allocated', $content);
        $this->assertMatchesRegularExpression('/data-effect-deploy-button[^>]*disabled/s', $content);
    }

    public function test_deploy_writes_fx_type_path(): void
    {
        [$user, $show, $fakeOsc] = $this->showWithLiveBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 3]);
        $typePath = X32OscAddressMap::fxType(3);
        $fakeOsc->seedInt($typePath, 0);

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.deploy-package-item', [$show, $item]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('osc_paths.type', $typePath);

        $typeWrite = collect($fakeOsc->writes())->first(fn (array $write) => ($write['path'] ?? null) === $typePath);

        $this->assertNotNull($typeWrite);
        $this->assertSame('int', $typeWrite['type']);
        $this->assertSame(5, $typeWrite['value']);
    }

    public function test_deploy_writes_fx_parameter_paths(): void
    {
        [$user, $show, $fakeOsc] = $this->showWithLiveBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 3]);
        $typePath = X32OscAddressMap::fxType(3);
        $paramPath = X32OscAddressMap::fxParameter(3, 1);
        $fakeOsc->seedInt($typePath, 0);
        $fakeOsc->seedFloat($paramPath, 0.0);

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.deploy-package-item', [$show, $item]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $paramWrites = collect($fakeOsc->writes())
            ->filter(fn (array $write) => str_starts_with((string) ($write['path'] ?? ''), '/fx/3/par/'))
            ->values();

        $this->assertNotEmpty($paramWrites);
        $this->assertSame('float', $paramWrites->first()['type']);
    }

    public function test_deploy_limiter_to_fx7_writes_type_and_logf_parameters(): void
    {
        [$user, $show, $fakeOsc] = $this->showWithLiveBaseline();
        $package = $this->createPackageWithEffects(['LIM'], 'Limiter Deploy Pack');
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 7]);
        $typePath = X32OscAddressMap::fxType(7);
        $fakeOsc->seedInt($typePath, 0);

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.deploy-package-item', [$show, $item]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('slot', 7);

        $attackWrite = collect($fakeOsc->writes())->firstWhere(
            fn (array $write) => ($write['path'] ?? null) === X32OscAddressMap::fxParameter(7, 5),
        );

        $this->assertNotNull($attackWrite);
        $this->assertSame('float', $attackWrite['type']);
    }

    public function test_deploy_requires_read_back_confirmation(): void
    {
        [$user, $show, $fakeOsc] = $this->showWithLiveBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 3]);
        $typePath = X32OscAddressMap::fxType(3);
        $fakeOsc->seedInt($typePath, 0);

        $response = $this->actingAs($user)
            ->postJson(route('shows.console.effects.deploy-package-item', [$show, $item]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertSame('deployed', $response['status']);
        $this->assertNull($response['error']);
    }

    public function test_failed_read_back_returns_failure(): void
    {
        [$user, $show, $fakeOsc] = $this->showWithLiveBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 3]);
        foreach (X32OscAddressMap::fxTypePathCandidates(3) as $path) {
            $fakeOsc->queryFailPaths[] = $path;
        }

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.deploy-package-item', [$show, $item]))
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'Effect type write was not confirmed by the console.');
    }

    public function test_non_live_mode_blocks_deploy(): void
    {
        [$user, $show] = $this->showWithLiveBaseline(runtimeMode: 'dry_run');
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 3]);

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.deploy-package-item', [$show, $item]))
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Live X32 control is not enabled for this console device.');
    }

    public function test_deploy_writes_only_fx_type_and_parameter_paths(): void
    {
        [$user, $show, $fakeOsc] = $this->showWithLiveBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 3]);
        $fakeOsc->seedInt(X32OscAddressMap::fxType(3), 0);

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.deploy-package-item', [$show, $item]))
            ->assertOk()
            ->assertJsonPath('success', true);

        foreach ($fakeOsc->writes() as $write) {
            $path = (string) ($write['path'] ?? '');

            $this->assertMatchesRegularExpression('#^/fx/\d{1,2}/(type|par/\d{1,2})$#', $path, "Unexpected OSC path written: {$path}");
            $this->assertDoesNotMatchRegularExpression('#/(ch|bus|fxrtn|mtx|dca|main|insert|scene|-action)/#', $path);
        }
    }

    public function test_deploy_route_is_post_only_in_effects_scaffold(): void
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() !== 'shows/{show}/console/effects/package-items/{item}/deploy') {
                continue;
            }

            $this->assertSame(['POST'], $route->methods());

            return;
        }

        $this->fail('Deploy route was not registered.');
    }

    /**
     * @param  list<string>  $effectCodes
     */
    private function createPackageWithEffects(array $effectCodes, string $name = 'Deploy Test Pack'): EffectPackage
    {
        $packageType = EffectPackageTypeOption::query()->where('slug', EffectPackageTypeOption::SLUG_SONG_PACKAGE)->firstOrFail();
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
     * @return array{0: \App\Models\User, 1: Show, 2: FakeX32OscConsoleClient}
     */
    private function showWithLiveBaseline(string $runtimeMode = 'live'): array
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Deploy Show']);
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

        $summary = [
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'configuration' => ['fx' => []],
        ];

        $snapshot = ConsoleLearningSnapshot::factory()->create([
            'band_id' => $band->id,
            'show_id' => $show->id,
            'integration_device_id' => $device->id,
            'learned_summary_json' => $summary,
        ]);

        ShowConsoleBaseline::factory()->create([
            'band_id' => $band->id,
            'show_id' => $show->id,
            'source_snapshot_id' => $snapshot->id,
            'baseline_json' => $summary,
            'active' => true,
        ]);

        $fakeOsc = app(FakeX32OscConsoleClient::class);
        $fakeOsc->queryFailPaths = [];
        $fakeOsc->shouldFail = false;

        return [$user, $show, $fakeOsc];
    }
}
