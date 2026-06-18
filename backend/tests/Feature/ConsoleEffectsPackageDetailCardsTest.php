<?php

namespace Tests\Feature;

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
use Database\Seeders\EffectLibraryReferenceSeeder;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleEffectsPackageDetailCardsTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_selected_package_renders_one_routing_style_card_per_effect(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects($show, ['PLAT', 'DLY']);

        $response = $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]));

        $response->assertOk()
            ->assertSee('vx32-effects-workspace__effect-detail-cards', false)
            ->assertSee('vx32-routing-detail__input-cards', false);

        $this->assertSame(
            2,
            substr_count((string) $response->getContent(), 'vx32-routing-detail__input-card--effect'),
        );
    }

    public function test_effect_card_title_uses_operator_friendly_name(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects($show, ['PLAT']);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('<h4 class="vx32-routing-detail__input-card-title">Vocal Plate</h4>', false)
            ->assertSee('X32: Plate Reverb', false);
    }

    public function test_effect_card_includes_technical_metadata(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $plate = X32Effect::query()->where('effect_code', 'PLAT')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();
        $package = $this->createPackageWithEffects($show, ['PLAT']);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('PLAT', false)
            ->assertSee('Algorithm '.$plate->x32_algorithm_id, false)
            ->assertSee($plate->slotGroupLabel(), false)
            ->assertSee('X32: '.$plate->effect_name, false);
    }

    public function test_effect_card_renders_copied_parameters_in_parameter_number_order(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects($show, ['PLAT']);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->getContent();

        $preDelayPos = strpos($content, 'Pre Delay');
        $decayPos = strpos($content, 'Decay');
        $sizePos = strpos($content, 'Size');

        $this->assertNotFalse($preDelayPos);
        $this->assertNotFalse($decayPos);
        $this->assertNotFalse($sizePos);
        $this->assertLessThan($decayPos, $preDelayPos);
        $this->assertLessThan($sizePos, $decayPos);
    }

    public function test_null_parameter_values_render_as_empty_editable_input(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects($show, ['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->parameters()->where('parameter_number', 1)->update(['value' => null]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('Pre Delay', false)
            ->assertSee('data-effect-parameter-input', false)
            ->assertSee('placeholder="—"', false);
    }

    public function test_parameter_unit_is_shown_when_present(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects($show, ['4TAP']);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('Time', false)
            ->assertSee('value="500"', false)
            ->assertSee('vx32-effects-workspace__parameter-card-unit', false)
            ->assertSee('>ms</span>', false);
    }

    public function test_parameters_render_as_editable_mini_cards(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects($show, ['PLAT']);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('vx32-effects-workspace__parameter-cards', false)
            ->assertSee('vx32-effects-workspace__parameter-card', false)
            ->getContent();

        $this->assertGreaterThan(1, substr_count($content, 'vx32-effects-workspace__parameter-card'));
    }

    public function test_parameter_value_can_be_updated_via_patch(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects($show, ['PLAT']);
        $parameter = $package->effectPackageItems->firstOrFail()->parameters()->where('parameter_number', 1)->firstOrFail();

        $this->actingAs($user)
            ->patchJson(route('shows.console.effects.update-parameter', [$show, $parameter]), [
                'value' => '125',
            ])
            ->assertOk()
            ->assertJsonPath('parameter.value', '125');

        $this->assertDatabaseHas('effect_package_item_parameters', [
            'id' => $parameter->id,
            'value' => '125',
        ]);
    }

    public function test_edit_button_exists_on_effect_card(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects($show, ['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('vx32-routing-detail__configure', false)
            ->assertSee(route('shows.console.effects.edit-package-item', [$show, $item], false), false)
            ->assertSee('>Edit<', false)
            ->assertSee('vx32-effects-workspace__effect-delete-btn', false)
            ->assertSee('Parameters:', false)
            ->assertSee('vx32-routing-detail__input-routing-pill--routed', false);
    }

    public function test_empty_package_renders_no_effects_message(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = EffectPackage::factory()->create([
            'name' => 'Empty Package',
            'slug' => 'empty-package',
        ]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('No effects have been added to this package yet.', false)
            ->assertDontSee('vx32-routing-detail__input-card--effect', false);
    }

    public function test_effect_without_copied_parameters_renders_no_parameters_message(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects($show, ['PLAT']);
        $package->effectPackageItems->firstOrFail()->parameters()->delete();

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('No parameters available for this effect yet.', false);
    }

    public function test_no_effects_live_control_routes_are_added_by_detail_cards_work(): void
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
                if ($uri === 'shows/{show}/console/effects/parameters/{parameter}') {
                    $this->assertSame(['PATCH'], $route->methods(), "Package parameter route must be PATCH only: {$uri}");
                }

                if ($uri === 'shows/{show}/console/effects/package-items/{item}/slot') {
                    $this->assertSame(['POST'], $route->methods(), "Package item slot route must be POST only: {$uri}");
                }

                if ($uri === 'shows/{show}/console/effects/package-items/{item}/routing-plan') {
                    $this->assertSame(['POST'], $route->methods(), "Routing plan route must be POST only: {$uri}");
                }

                continue;
            }

            $this->assertDoesNotMatchRegularExpression('#(^|/)effects(/|$)#', $uri, "Unexpected effects route: {$uri}");
        }
    }

    /**
     * @param  list<string>  $effectCodes
     */
    private function createPackageWithEffects(Show $show, array $effectCodes): EffectPackage
    {
        $packageType = EffectPackageTypeOption::query()->where('slug', EffectPackageTypeOption::SLUG_SONG_PACKAGE)->firstOrFail();

        $effectIds = collect($effectCodes)
            ->map(fn (string $code) => X32Effect::query()
                ->where('effect_code', $code)
                ->where('x32_slot_group', X32SlotGroup::Fx1To4)
                ->firstOrFail()
                ->id)
            ->all();

        return app(CreateEffectPackageService::class)->create([
            'name' => 'Detail Cards '.implode(' ', $effectCodes),
            'description' => 'Package for detail card tests.',
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
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Detail Cards Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Detail Cards Baseline');

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
