<?php

namespace Tests\Feature;

use App\Enums\EffectRoutingMode;
use App\Enums\EffectRoutingTargetSection;
use App\Enums\EffectReturnDestination;
use App\Enums\X32SlotGroup;
use App\Models\Band;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use App\Models\EffectPackageItemParameter;
use App\Models\EffectPackageItemTargetSection;
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

class ConsoleEffectsPackageManagementTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_package_rows_show_edit_and_delete_buttons(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);

        $this->actingAs($user)
            ->get(route('shows.console.effects', $show))
            ->assertOk()
            ->assertSee('vx32-effects-workspace__package-action', false)
            ->assertSee(route('shows.console.effects.edit-package', [$show, $package], false), false)
            ->assertSee(route('shows.console.effects.destroy-package', [$show, $package], false), false)
            ->assertSee('>Edit<', false)
            ->assertSee('>Delete<', false);
    }

    public function test_package_edit_form_renders(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT'], 'Reggae Dub');

        $this->actingAs($user)
            ->get(route('shows.console.effects.edit-package', [$show, $package]))
            ->assertOk()
            ->assertSee('Edit Effect Package', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="effect_package_type_id"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('REGGAE DUB', false);
    }

    public function test_package_name_is_saved_uppercase(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT'], 'Old Name');
        $typeId = EffectPackageTypeOption::query()->where('slug', EffectPackageTypeOption::SLUG_SONG_PACKAGE)->value('id');

        $this->actingAs($user)
            ->patch(route('shows.console.effects.update-package', [$show, $package]), [
                'name' => 'horn funk',
                'description' => $package->description,
                'effect_package_type_id' => $typeId,
                'is_active' => '1',
            ])
            ->assertRedirect(route('shows.console.effects', ['show' => $show, 'package' => $package->id]));

        $this->assertDatabaseHas('effect_packages', [
            'id' => $package->id,
            'name' => 'HORN FUNK',
        ]);
    }

    public function test_package_description_updates(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $typeId = $package->effect_package_type_id;

        $this->actingAs($user)
            ->patch(route('shows.console.effects.update-package', [$show, $package]), [
                'name' => $package->name,
                'description' => 'Dub planning package for reggae songs.',
                'effect_package_type_id' => $typeId,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('effect_packages', [
            'id' => $package->id,
            'description' => 'Dub planning package for reggae songs.',
        ]);
    }

    public function test_package_type_updates(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $permanentTypeId = EffectPackageTypeOption::query()
            ->where('slug', EffectPackageTypeOption::SLUG_PERMANENT)
            ->value('id');

        $this->actingAs($user)
            ->patch(route('shows.console.effects.update-package', [$show, $package]), [
                'name' => $package->name,
                'description' => null,
                'effect_package_type_id' => $permanentTypeId,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('effect_packages', [
            'id' => $package->id,
            'effect_package_type_id' => $permanentTypeId,
            'package_type' => 'permanent',
        ]);
    }

    public function test_package_delete_removes_items_parameters_and_target_sections(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);
        $item = $package->effectPackageItems->first();
        $parameter = $item->parameters()->firstOrFail();
        EffectPackageItemTargetSection::query()->create([
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Vocals,
        ]);
        $x32EffectId = $item->effect_id;
        $referenceParameterCount = X32EffectParameter::query()->where('effect_id', $x32EffectId)->count();

        $this->actingAs($user)
            ->delete(route('shows.console.effects.destroy-package', [$show, $package]))
            ->assertRedirect(route('shows.console.effects', $show));

        $this->assertDatabaseMissing('effect_packages', ['id' => $package->id]);
        $this->assertDatabaseMissing('effect_package_items', ['effect_package_id' => $package->id]);
        $this->assertDatabaseMissing('effect_package_item_parameters', ['id' => $parameter->id]);
        $this->assertDatabaseMissing('effect_package_item_target_sections', ['effect_package_item_id' => $item->id]);
        $this->assertDatabaseHas('effects', ['id' => $x32EffectId]);
        $this->assertSame($referenceParameterCount, X32EffectParameter::query()->where('effect_id', $x32EffectId)->count());
    }

    public function test_effect_card_shows_edit_and_delete_controls(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('vx32-effects-workspace__effect-card-actions', false)
            ->assertSee(route('shows.console.effects.edit-package-item', [$show, $item], false), false)
            ->assertSee(route('shows.console.effects.destroy-package-item', [$show, $item], false), false)
            ->assertSee('>Edit<', false)
            ->assertSee('vx32-effects-workspace__effect-delete-btn', false);
    }

    public function test_package_effect_edit_form_renders(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->get(route('shows.console.effects.edit-package-item', [$show, $item]))
            ->assertOk()
            ->assertSee('Edit Package Effect', false)
            ->assertSee('name="preferred_slot_number"', false)
            ->assertSee('name="routing_mode"', false)
            ->assertSee('name="target_sections[]"', false)
            ->assertSee('name="return_destination"', false)
            ->assertSee('name="default_return_level"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('name="parameters[', false);
    }

    public function test_package_effect_edit_updates_preferred_slot(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->patch(route('shows.console.effects.update-package-item', [$show, $item]), $this->baseItemPayload($item, [
                'preferred_slot_number' => 2,
            ]))
            ->assertRedirect(route('shows.console.effects', ['show' => $show, 'package' => $package->id]));

        $this->assertDatabaseHas('effect_package_items', [
            'id' => $item->id,
            'preferred_slot_number' => 2,
        ]);
    }

    public function test_package_effect_edit_updates_routing_plan(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->patch(route('shows.console.effects.update-package-item', [$show, $item]), $this->baseItemPayload($item, [
                'routing_mode' => EffectRoutingMode::Insert->value,
                'return_destination' => EffectReturnDestination::MonitorOnly->value,
                'default_return_level' => '-6',
                'notes' => 'Insert planning only.',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('effect_package_items', [
            'id' => $item->id,
            'routing_mode' => EffectRoutingMode::Insert->value,
            'return_destination' => EffectReturnDestination::MonitorOnly->value,
            'default_return_level' => '-6.00',
            'notes' => 'Insert planning only.',
        ]);
    }

    public function test_package_effect_edit_updates_multiple_target_sections(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->patch(route('shows.console.effects.update-package-item', [$show, $item]), $this->baseItemPayload($item, [
                'target_sections' => [
                    EffectRoutingTargetSection::Vocals->value,
                    EffectRoutingTargetSection::Horns->value,
                ],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('effect_package_item_target_sections', [
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Vocals->value,
        ]);
        $this->assertDatabaseHas('effect_package_item_target_sections', [
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Horns->value,
        ]);
    }

    public function test_package_effect_edit_updates_copied_parameter_values(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $parameter = $item->parameters()->orderBy('parameter_number')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('shows.console.effects.update-package-item', [$show, $item]), $this->baseItemPayload($item, [
                'parameters' => [
                    $parameter->id => '120',
                ],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('effect_package_item_parameters', [
            'id' => $parameter->id,
            'value' => '120',
        ]);
    }

    public function test_package_effect_delete_removes_copied_parameters_and_target_sections(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);
        $item = $package->effectPackageItems->firstWhere(fn ($row) => $row->x32Effect?->effect_code === 'PLAT');
        $parameter = $item->parameters()->firstOrFail();
        EffectPackageItemTargetSection::query()->create([
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Vocals,
        ]);
        $x32EffectId = $item->effect_id;
        $referenceParameterCount = X32EffectParameter::query()->where('effect_id', $x32EffectId)->count();

        $this->actingAs($user)
            ->delete(route('shows.console.effects.destroy-package-item', [$show, $item]))
            ->assertRedirect(route('shows.console.effects', ['show' => $show, 'package' => $package->id]));

        $this->assertDatabaseMissing('effect_package_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('effect_package_item_parameters', ['id' => $parameter->id]);
        $this->assertDatabaseMissing('effect_package_item_target_sections', ['effect_package_item_id' => $item->id]);
        $this->assertDatabaseHas('effects', ['id' => $x32EffectId]);
        $this->assertSame($referenceParameterCount, X32EffectParameter::query()->where('effect_id', $x32EffectId)->count());
        $this->assertDatabaseHas('effect_package_items', ['effect_package_id' => $package->id]);
    }

    public function test_slot_validation_still_applies_on_package_effect_edit(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);
        $plateItem = $package->effectPackageItems->firstWhere(fn ($row) => $row->x32Effect?->effect_code === 'PLAT');
        $delayItem = $package->effectPackageItems->firstWhere(fn ($row) => $row->x32Effect?->effect_code === 'DLY');
        $plateItem->update(['preferred_slot_number' => 1]);

        $this->actingAs($user)
            ->from(route('shows.console.effects.edit-package-item', [$show, $delayItem]))
            ->patch(route('shows.console.effects.update-package-item', [$show, $delayItem]), $this->baseItemPayload($delayItem, [
                'preferred_slot_number' => 1,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('preferred_slot_number');
    }

    public function test_no_effects_live_control_routes_are_added_by_management_work(): void
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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function baseItemPayload(EffectPackageItem $item, array $overrides = []): array
    {
        $item->loadMissing('parameters');

        $parameters = $item->parameters
            ->mapWithKeys(fn (EffectPackageItemParameter $parameter) => [$parameter->id => $parameter->value])
            ->all();

        return array_merge([
            'preferred_slot_number' => $item->preferred_slot_number,
            'routing_mode' => $item->routing_mode?->value,
            'target_sections' => $item->resolvedTargetSectionValues(),
            'return_destination' => $item->return_destination?->value,
            'default_return_level' => $item->default_return_level,
            'notes' => $item->notes,
            'parameters' => $parameters,
        ], $overrides);
    }

    /**
     * @param  list<string>  $effectCodes
     */
    private function createPackageWithEffects(array $effectCodes, string $name = 'Management Pack'): EffectPackage
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
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Management Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Management Baseline');

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
