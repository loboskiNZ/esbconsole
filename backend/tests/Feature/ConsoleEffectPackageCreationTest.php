<?php

namespace Tests\Feature;

use App\Enums\EffectImplementationType;
use App\Enums\X32SlotGroup;
use App\Models\Band;
use App\Models\EffectPackage;
use App\Models\EffectPackageItemParameter;
use App\Models\EffectPackageTypeOption;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\X32Effect;
use App\Models\X32EffectParameter;
use App\Services\Console\ShowConsoleBaselineService;
use App\Services\Console\X32ConsoleLearningService;
use App\Services\Effects\CreateEffectPackageService;
use Database\Seeders\EffectLibraryReferenceSeeder;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleEffectPackageCreationTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_effects_page_shows_new_button_linking_to_new_package_form(): void
    {
        [$user, $show] = $this->createShowWithBaseline();

        $this->actingAs($user)
            ->get(route('shows.console.effects', $show))
            ->assertOk()
            ->assertSee('vx32-effects-workspace__new-btn', false)
            ->assertSee(route('shows.console.effects.new-package', $show, false), false)
            ->assertSee('New', false);
    }

    public function test_new_package_form_lists_x32_effects_not_packages(): void
    {
        [$user, $show] = $this->createShowWithBaseline();

        $response = $this->actingAs($user)
            ->get(route('shows.console.effects.new-package', $show));

        $response->assertOk()
            ->assertSee('vx32-effects-combobox', false)
            ->assertSee('Type to search X32 effects', false)
            ->assertSee('Vocal Plate', false)
            ->assertSee('PLAT', false)
            ->assertSee('Dub Delay', false)
            ->assertSee('MODD', false)
            ->assertSee('x32-effects-options', false)
            ->assertDontSee('Standard Vocal/Horn', false)
            ->assertDontSee('Reggae Dub', false)
            ->assertDontSee('FOH Main Package', false);
    }

    public function test_save_creates_package_items_and_copied_parameters_from_effect_parameters(): void
    {
        [$user, $show] = $this->createShowWithBaseline();

        $packageType = EffectPackageTypeOption::query()->where('slug', EffectPackageTypeOption::SLUG_SONG_PACKAGE)->firstOrFail();
        $plate = X32Effect::query()->where('effect_code', 'PLAT')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();
        $delay = X32Effect::query()->where('effect_code', 'DLY')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();

        $response = $this->actingAs($user)->post(route('shows.console.effects.store-package', $show), [
            'name' => 'My Custom Vocal Pack',
            'description' => 'Custom plate and delay for testing.',
            'effect_package_type_id' => $packageType->id,
            'effect_ids' => [$plate->id, $delay->id],
        ]);

        $package = EffectPackage::query()->where('slug', 'my-custom-vocal-pack')->first();

        $response->assertRedirect(route('shows.console.effects', ['show' => $show, 'package' => $package->id]));

        $this->assertNotNull($package);
        $this->assertSame('MY CUSTOM VOCAL PACK', $package->name);
        $this->assertCount(2, $package->effectPackageItems);

        $plateItem = $package->effectPackageItems->firstWhere('effect_id', $plate->id);
        $this->assertNotNull($plateItem);
        $this->assertGreaterThan(0, $plateItem->parameters()->count());

        $parameterCount = X32EffectParameter::query()
            ->where('effect_id', $plate->id)
            ->where('is_active', true)
            ->count();
        $this->assertSame($parameterCount, $plateItem->parameters()->count());

        $this->assertDatabaseHas('effect_package_item_parameters', [
            'effect_package_item_id' => $plateItem->id,
            'parameter_number' => 2,
            'parameter_name' => 'Decay',
        ]);
    }

    public function test_created_package_appears_on_effects_page_with_algorithm_details(): void
    {
        [$user, $show] = $this->createShowWithBaseline();

        $packageType = EffectPackageTypeOption::query()->where('slug', EffectPackageTypeOption::SLUG_PERMANENT)->firstOrFail();
        $plate = X32Effect::query()->where('effect_code', 'PLAT')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();

        $this->actingAs($user)->post(route('shows.console.effects.store-package', $show), [
            'name' => 'Display Pack',
            'description' => 'Shown in details column.',
            'effect_package_type_id' => $packageType->id,
            'effect_ids' => [$plate->id],
        ]);

        $package = EffectPackage::query()->where('slug', 'display-pack')->firstOrFail();

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('DISPLAY PACK', false)
            ->assertSee('vx32-routing-detail__input-card--effect', false)
            ->assertSee('Vocal Plate', false)
            ->assertSee('PLAT', false)
            ->assertSee('X32: Plate Reverb', false)
            ->assertSee('Parameters:', false)
            ->assertSee('Pre Delay', false)
            ->assertSee('Decay', false)
            ->assertSee('vx32-routing-detail__configure', false)
            ->assertSee('>Edit<', false)
            ->assertSee('vx32-effects-workspace__effect-delete-btn', false);
    }

    public function test_non_slot_implementation_type_does_not_count_against_fx_slot_limit(): void
    {
        $nonSlot = X32Effect::query()->create([
            'effect_code' => 'TEST',
            'effect_name' => 'Test Non-Slot Processor',
            'x32_algorithm_id' => 99,
            'x32_slot_group' => X32SlotGroup::Fx5To8,
            'category' => 'processor',
            'implementation_type' => EffectImplementationType::MainProcessing,
            'is_active' => true,
        ]);

        $slotEffects = X32Effect::query()
            ->where('implementation_type', EffectImplementationType::FxSlot)
            ->orderBy('id')
            ->limit(8)
            ->pluck('id')
            ->all();

        $packageType = EffectPackageTypeOption::query()->firstOrFail();

        app(CreateEffectPackageService::class)->create([
            'name' => 'Eight Plus Non Slot',
            'description' => null,
            'effect_package_type_id' => $packageType->id,
            'effect_ids' => array_merge($slotEffects, [$nonSlot->id]),
        ]);

        $this->assertDatabaseHas('effect_packages', ['slug' => 'eight-plus-non-slot']);
    }

    public function test_fx_slot_package_limit_is_enforced(): void
    {
        [$user, $show] = $this->createShowWithBaseline();

        $packageType = EffectPackageTypeOption::query()->where('slug', EffectPackageTypeOption::SLUG_SONG_PACKAGE)->firstOrFail();

        $fxEffectIds = X32Effect::query()
            ->where('implementation_type', EffectImplementationType::FxSlot)
            ->orderBy('id')
            ->limit(9)
            ->pluck('id')
            ->all();

        $this->assertCount(9, $fxEffectIds);

        $this->actingAs($user)
            ->from(route('shows.console.effects.new-package', $show))
            ->post(route('shows.console.effects.store-package', $show), [
                'name' => 'Too Many Slots',
                'description' => null,
                'effect_package_type_id' => $packageType->id,
                'effect_ids' => $fxEffectIds,
            ])
            ->assertRedirect(route('shows.console.effects.new-package', $show))
            ->assertSessionHasErrors('effect_ids');

        $this->assertDatabaseMissing('effect_packages', ['slug' => 'too-many-slots']);
        $this->assertSame(0, EffectPackageItemParameter::query()->count());
    }

    public function test_no_effects_live_control_routes_are_added_by_package_creation_flow(): void
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

    public function test_no_effect_osc_write_services_are_added_by_package_creation_flow(): void
    {
        $this->assertFalse(class_exists(\App\Services\Console\ShowConsoleEffectControlService::class));
        $this->assertFalse(class_exists(\App\Services\X32\X32EffectOscWriter::class));
    }

    /**
     * @return array{0: \App\Models\User, 1: Show}
     */
    private function createShowWithBaseline(): array
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Package Creation Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Package Baseline');

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
