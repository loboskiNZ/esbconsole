<?php

namespace Tests\Feature;

use App\Enums\EffectImplementationType;
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

class ConsoleEffectsPackageSlotAllocationTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_effect_card_shows_slot_allocation_control(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('Slot allocation', false)
            ->assertSee('data-effect-slot-input', false)
            ->assertSee('Not allocated', false)
            ->assertSee('Allowed: FX1–FX4', false);
    }

    public function test_fx1_4_effect_only_offers_fx1_through_fx4_slots(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->getContent();

        $selectHtml = $this->extractSlotSelectHtml($content);

        $this->assertStringContainsString('value="1"', $selectHtml);
        $this->assertStringContainsString('value="4"', $selectHtml);
        $this->assertStringNotContainsString('value="5"', $selectHtml);
        $this->assertStringNotContainsString('value="8"', $selectHtml);
    }

    public function test_fx5_8_effect_only_offers_fx5_through_fx8_slots(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['GEQ']);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->getContent();

        $selectHtml = $this->extractSlotSelectHtml($content);

        $this->assertStringContainsString('Allowed: FX5–FX8', $content);
        $this->assertStringContainsString('value="5"', $selectHtml);
        $this->assertStringContainsString('value="8"', $selectHtml);
        $this->assertStringNotContainsString('value="1"', $selectHtml);
        $this->assertStringNotContainsString('value="4"', $selectHtml);
    }

    public function test_any_slot_group_effect_offers_fx1_through_fx8(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $effect = $this->createAnySlotEffect();
        $package = $this->createPackageWithEffectIds([$effect->id]);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->getContent();

        $selectHtml = $this->extractSlotSelectHtml($content);

        $this->assertStringContainsString('Allowed: FX1–FX8', $content);
        $this->assertStringContainsString('value="1"', $selectHtml);
        $this->assertStringContainsString('value="8"', $selectHtml);
    }

    public function test_saving_valid_slot_updates_preferred_slot_number(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.update-package-item-slot', [$show, $item]), [
                'preferred_slot_number' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('item.preferred_slot_number', 3);

        $this->assertDatabaseHas('effect_package_items', [
            'id' => $item->id,
            'preferred_slot_number' => 3,
        ]);
    }

    public function test_saving_null_clears_preferred_slot_number(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 2]);

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.update-package-item-slot', [$show, $item]), [
                'preferred_slot_number' => null,
            ])
            ->assertOk()
            ->assertJsonPath('item.preferred_slot_number', null);

        $this->assertDatabaseHas('effect_package_items', [
            'id' => $item->id,
            'preferred_slot_number' => null,
        ]);
    }

    public function test_invalid_slot_for_slot_group_is_rejected_with_operator_friendly_message(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.update-package-item-slot', [$show, $item]), [
                'preferred_slot_number' => 6,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Vocal Plate can only use FX1–FX4.');
    }

    public function test_duplicate_slot_in_same_package_is_rejected_with_operator_friendly_message(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);
        $plateItem = $package->effectPackageItems->firstWhere(fn ($item) => $item->x32Effect?->effect_code === 'PLAT');
        $delayItem = $package->effectPackageItems->firstWhere(fn ($item) => $item->x32Effect?->effect_code === 'DLY');

        $plateItem->update(['preferred_slot_number' => 1]);

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.update-package-item-slot', [$show, $delayItem]), [
                'preferred_slot_number' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'FX1 is already used by Vocal Plate in this package.');
    }

    public function test_same_slot_in_different_song_packages_is_allowed(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $packageA = $this->createPackageWithEffects(['PLAT'], 'Package A');
        $packageB = $this->createPackageWithEffects(['PLAT'], 'Package B');
        $itemB = $packageB->effectPackageItems->firstOrFail();

        $packageA->effectPackageItems->firstOrFail()->update(['preferred_slot_number' => 1]);

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.update-package-item-slot', [$show, $itemB]), [
                'preferred_slot_number' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('item.preferred_slot_number', 1);
    }

    public function test_allocated_slot_displays_on_effect_card(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT']);
        $item = $package->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 2]);

        $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->assertSee('Allocated: FX', false)
            ->assertSee('data-effect-slot-pill-value>2</span>', false)
            ->assertSee('vx32-effects-workspace__slot-allocation--allocated', false);
    }

    public function test_conflict_option_is_disabled_in_select(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);
        $plateItem = $package->effectPackageItems->firstWhere(fn ($item) => $item->x32Effect?->effect_code === 'PLAT');
        $plateItem->update(['preferred_slot_number' => 1]);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $package->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('FX1 (in use)', $content);
        $this->assertStringContainsString('disabled', $content);
    }

    public function test_permanent_reserved_slot_is_disabled_in_select(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $permanent = $this->createPackageWithEffects(['GEQ'], 'FOH Main', EffectPackageTypeOption::SLUG_PERMANENT);
        $song = $this->createPackageWithEffects(['GEQ'], 'Reggae Dub');
        $permanent->effectPackageItems->firstOrFail()->update(['preferred_slot_number' => 5]);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $song->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('FX5 (reserved)', $content);
        $this->assertStringContainsString('data-permanent-reserved="1"', $content);
        $this->assertStringContainsString('FX5 is reserved by permanent package FOH MAIN.', $content);
    }

    public function test_other_song_package_slot_is_not_disabled_in_select(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $packageA = $this->createPackageWithEffects(['PLAT'], 'Reggae Dub');
        $packageB = $this->createPackageWithEffects(['DLY'], 'Disco Techno');
        $packageA->effectPackageItems->firstOrFail()->update(['preferred_slot_number' => 3]);

        $content = (string) $this->actingAs($user)
            ->get(route('shows.console.effects', ['show' => $show, 'package' => $packageB->id]))
            ->assertOk()
            ->getContent();

        $selectHtml = $this->extractSlotSelectHtml($content);

        $this->assertStringContainsString('value="3"', $selectHtml);
        $this->assertStringNotContainsString('FX3 (in use)', $selectHtml);
        $this->assertStringNotContainsString('FX3 (reserved)', $selectHtml);
        $this->assertStringNotContainsString('data-permanent-reserved="1"', $selectHtml);
    }

    public function test_permanent_package_slot_blocks_song_package_save(): void
    {
        [$user, $show] = $this->createShowWithBaseline();
        $permanent = $this->createPackageWithEffects(['GEQ'], 'FOH Main', EffectPackageTypeOption::SLUG_PERMANENT);
        $song = $this->createPackageWithEffects(['GEQ'], 'Reggae Dub');
        $permanent->effectPackageItems->firstOrFail()->update(['preferred_slot_number' => 5]);
        $songItem = $song->effectPackageItems->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('shows.console.effects.update-package-item-slot', [$show, $songItem]), [
                'preferred_slot_number' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'FX5 is reserved by permanent package FOH MAIN.');
    }

    public function test_no_effects_live_control_routes_are_added_by_slot_allocation_work(): void
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
    private function createPackageWithEffects(
        array $effectCodes,
        string $name = 'Slot Allocation Pack',
        string $typeSlug = EffectPackageTypeOption::SLUG_SONG_PACKAGE,
    ): EffectPackage {
        $effectIds = collect($effectCodes)
            ->map(fn (string $code) => X32Effect::query()
                ->where('effect_code', $code)
                ->where('x32_slot_group', in_array($code, ['GEQ', 'LIM'], true) ? X32SlotGroup::Fx5To8 : X32SlotGroup::Fx1To4)
                ->firstOrFail()
                ->id)
            ->all();

        return $this->createPackageWithEffectIds($effectIds, $name, $typeSlug);
    }

    /**
     * @param  list<int>  $effectIds
     */
    private function createPackageWithEffectIds(
        array $effectIds,
        string $name = 'Slot Allocation Pack',
        string $typeSlug = EffectPackageTypeOption::SLUG_SONG_PACKAGE,
    ): EffectPackage {
        $packageType = EffectPackageTypeOption::query()->where('slug', $typeSlug)->firstOrFail();

        return app(CreateEffectPackageService::class)->create([
            'name' => $name,
            'description' => null,
            'effect_package_type_id' => $packageType->id,
            'effect_ids' => $effectIds,
        ]);
    }

    private function createAnySlotEffect(): X32Effect
    {
        return X32Effect::query()->create([
            'effect_code' => 'ANYX',
            'effect_name' => 'Any Slot Effect',
            'operator_name' => 'Any Slot Effect',
            'x32_algorithm_id' => 98,
            'x32_slot_group' => X32SlotGroup::Any,
            'category' => 'delay',
            'implementation_type' => EffectImplementationType::FxSlot,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: \App\Models\User, 1: Show}
     */
    private function createShowWithBaseline(): array
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Effects Slot Allocation Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Slot Allocation Baseline');

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

    private function extractSlotSelectHtml(string $content): string
    {
        preg_match('/<select[^>]*data-effect-slot-input[^>]*>(.*?)<\/select>/s', $content, $matches);

        return $matches[1] ?? '';
    }
}
