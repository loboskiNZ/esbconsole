<?php

namespace Tests\Feature;

use App\Enums\EffectRoutingMode;
use App\Enums\EffectRoutingTargetSection;
use App\Enums\X32SlotGroup;
use App\Models\Band;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
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

class ConsoleEffectsAddEffectToPackageTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_selected_package_detail_shows_add_effect_button(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('>Add Effect<', false)
            ->assertSee(route('shows.console.effects.add-effect', [$show, $package], false), false);
    }

    public function test_add_effect_form_renders_with_catalogue_selector(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);

        $this->actingAs($user)
            ->get(route('shows.console.effects.add-effect', [$show, $package]))
            ->assertOk()
            ->assertSee('Add Effect', false)
            ->assertSee('Choose effect', false)
            ->assertSee('name="effect_id"', false)
            ->assertSee('name="preferred_slot_number"', false)
            ->assertSee('Save Effect', false)
            ->assertSee('Cancel', false)
            ->assertSee('Plate Reverb', false)
            ->assertSee('data-effects-combobox', false);
    }

    public function test_saving_adds_effect_package_item_to_existing_package(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $delay = X32Effect::query()->where('effect_code', 'DLY')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();

        $this->actingAs($user)
            ->post(route('shows.console.effects.store-effect', [$show, $package]), [
                'effect_id' => $delay->id,
            ])
            ->assertRedirect(route('shows.console.effects', ['show' => $show, 'package' => $package->id]));

        $this->assertDatabaseHas('effect_package_items', [
            'effect_package_id' => $package->id,
            'effect_id' => $delay->id,
        ]);

        $this->assertSame(2, EffectPackageItem::query()->where('effect_package_id', $package->id)->count());
    }

    public function test_saving_copies_effect_parameters_into_package_item_parameters(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $delay = X32Effect::query()->where('effect_code', 'DLY')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();
        $referenceCount = X32EffectParameter::query()->where('effect_id', $delay->id)->where('is_active', true)->count();

        $this->actingAs($user)
            ->post(route('shows.console.effects.store-effect', [$show, $package]), [
                'effect_id' => $delay->id,
            ])
            ->assertRedirect();

        $item = EffectPackageItem::query()
            ->where('effect_package_id', $package->id)
            ->where('effect_id', $delay->id)
            ->firstOrFail();

        $this->assertSame($referenceCount, EffectPackageItemParameter::query()->where('effect_package_item_id', $item->id)->count());
        $this->assertSame($referenceCount, X32EffectParameter::query()->where('effect_id', $delay->id)->count());
    }

    public function test_routing_suggestions_are_applied_to_new_item(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $geq = X32Effect::query()->where('effect_code', 'GEQ')->where('x32_slot_group', X32SlotGroup::Fx5To8)->firstOrFail();

        $this->actingAs($user)
            ->post(route('shows.console.effects.store-effect', [$show, $package]), [
                'effect_id' => $geq->id,
            ])
            ->assertRedirect();

        $item = EffectPackageItem::query()
            ->where('effect_package_id', $package->id)
            ->where('effect_id', $geq->id)
            ->firstOrFail();

        $this->assertSame(EffectRoutingMode::MainProcessing, $item->routing_mode);
    }

    public function test_target_section_suggestions_are_applied_for_main_processing_effects(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $geq = X32Effect::query()->where('effect_code', 'GEQ')->where('x32_slot_group', X32SlotGroup::Fx5To8)->firstOrFail();

        $this->actingAs($user)
            ->post(route('shows.console.effects.store-effect', [$show, $package]), [
                'effect_id' => $geq->id,
            ])
            ->assertRedirect();

        $item = EffectPackageItem::query()
            ->where('effect_package_id', $package->id)
            ->where('effect_id', $geq->id)
            ->firstOrFail();

        $this->assertDatabaseHas('effect_package_item_target_sections', [
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Foh->value,
        ]);
    }

    public function test_optional_valid_slot_allocation_is_saved(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $delay = X32Effect::query()->where('effect_code', 'DLY')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();

        $this->actingAs($user)
            ->post(route('shows.console.effects.store-effect', [$show, $package]), [
                'effect_id' => $delay->id,
                'preferred_slot_number' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('effect_package_items', [
            'effect_package_id' => $package->id,
            'effect_id' => $delay->id,
            'preferred_slot_number' => 3,
        ]);
    }

    public function test_invalid_slot_for_effect_group_is_rejected(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $delay = X32Effect::query()->where('effect_code', 'DLY')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();

        $this->actingAs($user)
            ->from(route('shows.console.effects.add-effect', [$show, $package]))
            ->post(route('shows.console.effects.store-effect', [$show, $package]), [
                'effect_id' => $delay->id,
                'preferred_slot_number' => 6,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('preferred_slot_number');
    }

    public function test_slot_conflict_rules_are_enforced_when_adding_effect(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);
        $plateItem = $package->effectPackageItems->firstWhere(fn ($item) => $item->x32Effect?->effect_code === 'PLAT');
        $plateItem->update(['preferred_slot_number' => 1]);
        $hall = X32Effect::query()->where('effect_code', 'HALL')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();

        $this->actingAs($user)
            ->from(route('shows.console.effects.add-effect', [$show, $package]))
            ->post(route('shows.console.effects.store-effect', [$show, $package]), [
                'effect_id' => $hall->id,
                'preferred_slot_number' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('preferred_slot_number');
    }

    public function test_duplicate_effect_in_same_package_is_rejected(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $plate = X32Effect::query()->where('effect_code', 'PLAT')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();

        $this->actingAs($user)
            ->from(route('shows.console.effects.add-effect', [$show, $package]))
            ->post(route('shows.console.effects.store-effect', [$show, $package]), [
                'effect_id' => $plate->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('effect_id');
    }

    public function test_cancel_link_returns_to_selected_package(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);

        $this->actingAs($user)
            ->get(route('shows.console.effects.add-effect', [$show, $package]))
            ->assertOk()
            ->assertSee(route('shows.console.effects', ['show' => $show, 'package' => $package->id], false), false);
    }

    public function test_permanent_slot_reservation_blocks_new_effect_slot_allocation(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $permanent = $this->createPackageWithEffects(['GEQ'], 'FOH Main', EffectPackageTypeOption::SLUG_PERMANENT);
        $song = $this->createPackageWithEffects(['PLAT'], 'Reggae Dub');
        $permanent->effectPackageItems->firstOrFail()->update(['preferred_slot_number' => 5]);
        $geq = X32Effect::query()->where('effect_code', 'GEQ')->where('x32_slot_group', X32SlotGroup::Fx5To8)->firstOrFail();

        $this->actingAs($user)
            ->from(route('shows.console.effects.add-effect', [$show, $song]))
            ->post(route('shows.console.effects.store-effect', [$show, $song]), [
                'effect_id' => $geq->id,
                'preferred_slot_number' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('preferred_slot_number');
    }

    public function test_no_effects_live_control_routes_are_added_by_add_effect_work(): void
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
        string $name = 'Add Effect Pack',
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
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Add Effect Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Add Effect Baseline');

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
