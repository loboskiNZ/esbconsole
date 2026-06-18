<?php

namespace Tests\Feature;

use App\Enums\EffectRoutingMode;
use App\Enums\EffectRoutingTargetSection;
use App\Enums\EffectReturnDestination;
use App\Enums\X32SlotGroup;
use App\Models\Band;
use App\Models\EffectPackageItem;
use App\Models\EffectPackageItemTargetSection;
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

class ConsoleEffectsPackageRoutingPlanTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_effect_card_shows_routing_plan_fields(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('Routing Plan', false)
            ->assertSee('Routing Mode', false)
            ->assertSee('Target Sections', false)
            ->assertSee('Return Destination', false)
            ->assertSee('Default Return Level', false)
            ->assertSee('data-routing-plan-field="routing_mode"', false)
            ->assertSee('data-routing-plan-target-section', false)
            ->assertSee('data-routing-plan-field="return_destination"', false)
            ->assertSee('data-routing-plan-field="default_return_level"', false)
            ->assertSee('data-routing-plan-field="notes"', false)
            ->assertSee('>Vocals<', false)
            ->assertSee('>Backing Vocals<', false)
            ->assertSee('>Horns<', false);
    }

    public function test_routing_plan_values_save_to_effect_package_items(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.update-package-item-routing-plan', [$show, $item]), [
                'routing_mode' => EffectRoutingMode::SendReturn->value,
                'target_sections' => [
                    EffectRoutingTargetSection::Vocals->value,
                    EffectRoutingTargetSection::Horns->value,
                ],
                'return_destination' => EffectReturnDestination::MainLr->value,
                'default_return_level' => -10,
                'notes' => 'Vocal send planning only.',
            ])
            ->assertOk()
            ->assertJsonPath('item.routing_mode', EffectRoutingMode::SendReturn->value)
            ->assertJsonPath('item.target_sections', [
                EffectRoutingTargetSection::Vocals->value,
                EffectRoutingTargetSection::Horns->value,
            ])
            ->assertJsonPath('item.target_sections_label', 'Vocals, Horns');

        $this->assertDatabaseHas('effect_package_items', [
            'id' => $item->id,
            'routing_mode' => EffectRoutingMode::SendReturn->value,
            'return_destination' => EffectReturnDestination::MainLr->value,
            'default_return_level' => '-10.00',
            'notes' => 'Vocal send planning only.',
        ]);

        $this->assertDatabaseHas('effect_package_item_target_sections', [
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Vocals->value,
        ]);
        $this->assertDatabaseHas('effect_package_item_target_sections', [
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Horns->value,
        ]);
    }

    public function test_saving_empty_target_sections_list_clears_rows(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        EffectPackageItemTargetSection::query()->create([
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Vocals,
        ]);

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.update-package-item-routing-plan', [$show, $item]), [
                'routing_mode' => EffectRoutingMode::SendReturn->value,
                'target_sections' => [],
                'return_destination' => EffectReturnDestination::MainLr->value,
            ])
            ->assertOk()
            ->assertJsonPath('item.target_sections', [])
            ->assertJsonPath('item.target_sections_label', 'Not selected');

        $this->assertSame(0, EffectPackageItemTargetSection::query()->where('effect_package_item_id', $item->id)->count());
    }

    public function test_invalid_target_section_is_rejected(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.update-package-item-routing-plan', [$show, $item]), [
                'target_sections' => ['not_configured'],
            ])
            ->assertUnprocessable();
    }

    public function test_graphic_eq_package_item_defaults_to_foh_target_section_row(): void
    {
        $package = $this->createPackageWithEffects(['GEQ']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->assertSame(EffectRoutingMode::MainProcessing, $item->routing_mode);
        $this->assertDatabaseHas('effect_package_item_target_sections', [
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Foh->value,
        ]);
    }

    public function test_precision_limiter_package_item_defaults_to_foh_target_section_row(): void
    {
        $package = $this->createPackageWithEffects(['LIM']);

        $item = $package->effectPackageItems->firstOrFail();

        $this->assertSame(EffectRoutingMode::MainProcessing, $item->routing_mode);
        $this->assertDatabaseHas('effect_package_item_target_sections', [
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Foh->value,
        ]);
    }

    public function test_reverb_package_item_does_not_force_target_sections(): void
    {
        $package = $this->createPackageWithEffects(['PLAT']);

        $item = $package->effectPackageItems->firstOrFail();

        $this->assertSame(EffectRoutingMode::SendReturn, $item->routing_mode);
        $this->assertSame(0, EffectPackageItemTargetSection::query()->where('effect_package_item_id', $item->id)->count());
    }

    public function test_delay_package_item_does_not_force_target_sections(): void
    {
        $package = $this->createPackageWithEffects(['DLY']);

        $item = $package->effectPackageItems->firstOrFail();

        $this->assertSame(EffectRoutingMode::SendReturn, $item->routing_mode);
        $this->assertSame(0, EffectPackageItemTargetSection::query()->where('effect_package_item_id', $item->id)->count());
    }

    public function test_legacy_target_section_column_displays_when_new_rows_are_absent(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->targetSections()->delete();
        $item->update(['target_section' => EffectRoutingTargetSection::Vocals]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('data-summary-target-sections>Vocals</dd>', false);
    }

    public function test_routing_plan_summary_displays_multiple_target_sections(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['GEQ']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update([
            'preferred_slot_number' => 5,
            'routing_mode' => EffectRoutingMode::MainProcessing,
            'return_destination' => EffectReturnDestination::MainLr,
            'default_return_level' => '0.00',
        ]);
        $item->targetSections()->delete();
        $item->targetSections()->createMany([
            ['target_section' => EffectRoutingTargetSection::Foh],
        ]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('Slot Allocation: FX', false)
            ->assertSee('Target Sections:', false)
            ->assertSee('Mode:', false)
            ->assertSee('Main Processing', false)
            ->assertSee('FOH', false)
            ->assertSee('Return:', false)
            ->assertSee('Main LR', false)
            ->assertSee('Default Return:', false)
            ->assertSee('0 dB', false);
    }

    public function test_no_effects_live_control_routes_are_added_by_routing_plan_work(): void
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
    private function createPackageWithEffects(array $effectCodes)
    {
        $packageType = EffectPackageTypeOption::query()->where('slug', EffectPackageTypeOption::SLUG_SONG_PACKAGE)->firstOrFail();

        $effectIds = collect($effectCodes)
            ->map(function (string $code) {
                $slotGroup = match ($code) {
                    'GEQ', 'LIM' => X32SlotGroup::Fx5To8,
                    default => X32SlotGroup::Fx1To4,
                };

                return X32Effect::query()
                    ->where('effect_code', $code)
                    ->where('x32_slot_group', $slotGroup)
                    ->firstOrFail()
                    ->id;
            })
            ->all();

        return app(CreateEffectPackageService::class)->create([
            'name' => 'Routing Plan '.implode(' ', $effectCodes),
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
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Routing Plan Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Routing Plan Baseline');

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
