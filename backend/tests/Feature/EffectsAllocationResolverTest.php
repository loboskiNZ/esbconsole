<?php

namespace Tests\Feature;

use App\Enums\EffectImplementationType;
use App\Enums\EffectPackageType;
use App\Enums\EffectsAllocationStatus;
use App\Enums\FallbackConsoleRecallType;
use App\Enums\X32SlotGroup;
use App\Models\EffectDefinition;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use App\Models\Song;
use App\Models\SongEffectAssignment;
use App\Services\Effects\EffectsAllocationResolver;
use Database\Seeders\EffectReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EffectsAllocationResolverTest extends TestCase
{
    use RefreshDatabase;

    private EffectsAllocationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new EffectsAllocationResolver;
    }

    public function test_resolver_allocates_songs_assigned_packages(): void
    {
        $this->seed(EffectReferenceSeeder::class);
        $song = Song::factory()->create(['song_code' => '301', 'name' => 'Dub Song']);

        $dubPackage = EffectPackage::query()->where('slug', 'reggae-dub-package')->firstOrFail();
        SongEffectAssignment::factory()->create([
            'song_id' => $song->id,
            'effect_package_id' => $dubPackage->id,
        ]);

        $result = $this->resolver->resolve($song->fresh());

        $this->assertSame($song->id, $result->songId);
        $this->assertSame('Dub Song', $result->songName);
        $this->assertContains('reggae-dub-package', array_column($result->assignedPackages, 'package_slug'));
        $this->assertNotEmpty($result->allocatedEffects);
        $this->assertContains(
            'reggae-dub-delay',
            array_map(static fn ($effect) => $effect->effectSlug, $result->allocatedEffects),
        );
    }

    public function test_permanent_packages_are_listed_before_song_selectable_packages(): void
    {
        $this->seed(EffectReferenceSeeder::class);
        $song = Song::factory()->create(['song_code' => '302']);

        $dubPackage = EffectPackage::query()->where('slug', 'reggae-dub-package')->firstOrFail();
        SongEffectAssignment::factory()->create([
            'song_id' => $song->id,
            'effect_package_id' => $dubPackage->id,
            'priority' => 1,
        ]);

        $result = $this->resolver->resolve($song->fresh());
        $slugs = array_column($result->assignedPackages, 'package_slug');

        $permanentIndex = array_search('standard-vocal-horn-package', $slugs, true);
        $selectableIndex = array_search('reggae-dub-package', $slugs, true);

        $this->assertNotFalse($permanentIndex);
        $this->assertNotFalse($selectableIndex);
        $this->assertLessThan($selectableIndex, $permanentIndex);
        $this->assertSame('permanent', $result->assignedPackages[$permanentIndex]['source']);
        $this->assertSame('song_assignment', $result->assignedPackages[$selectableIndex]['source']);
    }

    public function test_required_effects_are_allocated_before_optional_effects(): void
    {
        $package = EffectPackage::factory()->create(['slug' => 'priority-package', 'priority' => 10]);
        $required = $this->createSlotDefinition('required-first', 1);
        $optional = $this->createSlotDefinition('optional-second', 2);

        $this->attachItem($package, $optional, isRequired: false, preferredSlot: 1, priority: 10);
        $this->attachItem($package, $required, isRequired: true, preferredSlot: 2, priority: 20);

        $song = Song::factory()->create(['song_code' => '303']);
        $this->assignPackage($song, $package);

        $result = $this->resolver->resolve($song);
        $slotOrder = array_map(static fn ($effect) => $effect->slotNumber, $result->allocatedEffects);

        $this->assertSame([2, 1], $slotOrder);
        $this->assertSame('required-first', $result->allocatedEffects[0]->effectSlug);
    }

    public function test_preferred_slots_are_honoured_when_available(): void
    {
        $package = EffectPackage::factory()->create(['slug' => 'preferred-slot-package']);
        $definition = $this->createSlotDefinition('preferred-fx', 3);

        $this->attachItem($package, $definition, preferredSlot: 3);

        $song = Song::factory()->create(['song_code' => '304']);
        $this->assignPackage($song, $package);

        $result = $this->resolver->resolve($song);

        $this->assertCount(1, $result->allocatedEffects);
        $this->assertSame(3, $result->allocatedEffects[0]->slotNumber);
        $this->assertSame(EffectsAllocationStatus::Ready, $result->status);
    }

    public function test_preferred_slot_conflict_produces_warning_and_uses_alternate_valid_slot(): void
    {
        $package = EffectPackage::factory()->create(['slug' => 'conflict-package']);
        $first = $this->createSlotDefinition('occupies-two', 1);
        $second = $this->createSlotDefinition('wants-two', 2);

        $this->attachItem($package, $first, preferredSlot: 2, priority: 10);
        $this->attachItem($package, $second, preferredSlot: 2, priority: 20);

        $song = Song::factory()->create(['song_code' => '305']);
        $this->assignPackage($song, $package);

        $result = $this->resolver->resolve($song);

        $this->assertSame(EffectsAllocationStatus::ReadyWithWarnings, $result->status);
        $this->assertTrue(
            collect($result->warnings)->contains(
                static fn (string $warning): bool => str_contains($warning, 'wants-two') && str_contains($warning, 'allocated slot'),
            ),
        );
        $this->assertSame(
            [2, 1],
            array_map(static fn ($effect) => $effect->slotNumber, $result->allocatedEffects),
        );
    }

    public function test_required_effect_over_capacity_produces_blocked_status(): void
    {
        $package = EffectPackage::factory()->create(['slug' => 'over-capacity-package']);

        for ($index = 1; $index <= 5; $index++) {
            $definition = $this->createSlotDefinition('fx14-'.$index, $index);
            $this->attachItem($package, $definition, preferredSlot: $index, priority: $index * 10);
        }

        $song = Song::factory()->create(['song_code' => '306']);
        $this->assignPackage($song, $package);

        $result = $this->resolver->resolve($song);

        $this->assertSame(EffectsAllocationStatus::Blocked, $result->status);
        $this->assertCount(4, $result->allocatedEffects);
        $this->assertCount(1, $result->blockingConflicts);
        $this->assertSame('fx14-5', $result->blockingConflicts[0]->effectSlug);
    }

    public function test_optional_effect_over_capacity_is_dropped_with_warning(): void
    {
        $package = EffectPackage::factory()->create(['slug' => 'optional-drop-package']);

        for ($index = 1; $index <= 4; $index++) {
            $definition = $this->createSlotDefinition('required-'.$index, $index);
            $this->attachItem($package, $definition, preferredSlot: $index, priority: $index * 10);
        }

        $optional = $this->createSlotDefinition('optional-drop', 99);
        $this->attachItem($package, $optional, isRequired: false, preferredSlot: 1, priority: 99);

        $song = Song::factory()->create(['song_code' => '307']);
        $this->assignPackage($song, $package);

        $result = $this->resolver->resolve($song);

        $this->assertSame(EffectsAllocationStatus::ReadyWithWarnings, $result->status);
        $this->assertCount(4, $result->allocatedEffects);
        $this->assertCount(1, $result->droppedOptionalEffects);
        $this->assertSame('optional-drop', $result->droppedOptionalEffects[0]->effectSlug);
    }

    public function test_duplicate_effect_definitions_are_allocated_once_with_multiple_package_sources(): void
    {
        $shared = $this->createSlotDefinition('shared-plate', 5);
        $packageA = EffectPackage::factory()->create(['slug' => 'package-a', 'priority' => 10]);
        $packageB = EffectPackage::factory()->create(['slug' => 'package-b', 'priority' => 20]);

        $this->attachItem($packageA, $shared, preferredSlot: 1);
        $this->attachItem($packageB, $shared, preferredSlot: 1);

        $song = Song::factory()->create(['song_code' => '308']);
        $this->assignPackage($song, $packageA);
        $this->assignPackage($song, $packageB);

        $result = $this->resolver->resolve($song);

        $plates = array_values(array_filter(
            $result->allocatedEffects,
            static fn ($effect) => $effect->effectSlug === 'shared-plate',
        ));

        $this->assertCount(1, $plates);
        $this->assertEqualsCanonicalizing(['package-a', 'package-b'], $plates[0]->packageSources);
    }

    public function test_non_slot_effects_do_not_consume_fx_slots(): void
    {
        $this->seed(EffectReferenceSeeder::class);
        $song = Song::factory()->create(['song_code' => '309']);

        $result = $this->resolver->resolve($song);

        $limiter = collect($result->nonSlotEffects)->first(
            static fn ($effect) => $effect->effectSlug === 'foh-limiter-compressor',
        );

        $this->assertNotNull($limiter);
        $this->assertNull($limiter->slotNumber);
        $this->assertFalse($limiter->consumesFxSlot);
        $this->assertCount(0, array_filter(
            $result->allocatedEffects,
            static fn ($effect) => $effect->effectSlug === 'foh-limiter-compressor',
        ));
    }

    public function test_fx1_4_effects_only_allocate_into_slots_one_through_four(): void
    {
        $package = EffectPackage::factory()->create(['slug' => 'fx14-only']);
        $definition = $this->createSlotDefinition('fx14-slot', 5, X32SlotGroup::Fx1To4);

        $this->attachItem($package, $definition, preferredSlot: null);

        $song = Song::factory()->create(['song_code' => '310']);
        $this->assignPackage($song, $package);

        $result = $this->resolver->resolve($song);

        $this->assertSame(1, $result->allocatedEffects[0]->slotNumber);
        $this->assertLessThanOrEqual(4, $result->allocatedEffects[0]->slotNumber);
    }

    public function test_fx5_8_effects_only_allocate_into_slots_five_through_eight(): void
    {
        $package = EffectPackage::factory()->create(['slug' => 'fx58-only']);
        $definition = EffectDefinition::factory()->create([
            'slug' => 'fx58-slot',
            'x32_algorithm_code' => 'GEQ',
            'x32_algorithm_id' => 1,
            'x32_slot_group' => X32SlotGroup::Fx5To8,
            'implementation_type' => EffectImplementationType::FxSlot,
        ]);

        $this->attachItem($package, $definition, preferredSlot: null);

        $song = Song::factory()->create(['song_code' => '311']);
        $this->assignPackage($song, $package);

        $result = $this->resolver->resolve($song);

        $this->assertGreaterThanOrEqual(5, $result->allocatedEffects[0]->slotNumber);
        $this->assertLessThanOrEqual(8, $result->allocatedEffects[0]->slotNumber);
    }

    public function test_any_slot_group_allocates_into_first_available_slot_across_all_slots(): void
    {
        $package = EffectPackage::factory()->create(['slug' => 'any-slot-group']);
        $definition = EffectDefinition::factory()->create([
            'slug' => 'any-slot',
            'x32_slot_group' => X32SlotGroup::Any,
            'implementation_type' => EffectImplementationType::FxSlot,
        ]);

        $this->attachItem($package, $definition, preferredSlot: null);

        $song = Song::factory()->create(['song_code' => '312']);
        $this->assignPackage($song, $package);

        $result = $this->resolver->resolve($song);

        $this->assertSame(1, $result->allocatedEffects[0]->slotNumber);
    }

    public function test_fallback_console_recall_metadata_is_included(): void
    {
        $package = EffectPackage::factory()->create();
        $song = Song::factory()->create(['song_code' => '313']);

        SongEffectAssignment::factory()
            ->withFallbackRecall('Dub Fallback', FallbackConsoleRecallType::Scene)
            ->create([
                'song_id' => $song->id,
                'effect_package_id' => $package->id,
            ]);

        $result = $this->resolver->resolve($song->fresh());

        $this->assertCount(1, $result->fallbackConsoleRecall);
        $this->assertSame('Dub Fallback', $result->fallbackConsoleRecall[0]['name']);
        $this->assertSame('scene', $result->fallbackConsoleRecall[0]['type']);
    }

    public function test_conflicting_fallback_console_recall_metadata_produces_warning(): void
    {
        $packageA = EffectPackage::factory()->create(['slug' => 'fallback-a']);
        $packageB = EffectPackage::factory()->create(['slug' => 'fallback-b']);
        $song = Song::factory()->create(['song_code' => '314']);

        SongEffectAssignment::factory()
            ->withFallbackRecall('Scene A', FallbackConsoleRecallType::Scene)
            ->create(['song_id' => $song->id, 'effect_package_id' => $packageA->id, 'priority' => 10]);

        SongEffectAssignment::factory()
            ->withFallbackRecall('Scene B', FallbackConsoleRecallType::Snippet)
            ->create(['song_id' => $song->id, 'effect_package_id' => $packageB->id, 'priority' => 20]);

        $result = $this->resolver->resolve($song->fresh());

        $this->assertCount(2, $result->fallbackConsoleRecall);
        $this->assertTrue(
            collect($result->warnings)->contains(
                static fn (string $warning): bool => str_contains($warning, 'conflicting fallback console recall'),
            ),
        );
    }

    public function test_result_status_can_be_ready_ready_with_warnings_or_blocked(): void
    {
        $this->assertContains(EffectsAllocationStatus::Ready->value, EffectsAllocationStatus::values());
        $this->assertContains(EffectsAllocationStatus::ReadyWithWarnings->value, EffectsAllocationStatus::values());
        $this->assertContains(EffectsAllocationStatus::Blocked->value, EffectsAllocationStatus::values());
    }

    public function test_no_effects_live_control_routes_are_added_by_allocation_engine(): void
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

                if ($uri === 'shows/{show}/console/effects/package-items/{item}/routing-plan') {
                    $this->assertSame(['POST'], $route->methods(), "Routing plan route must be POST only: {$uri}");
                }

                continue;
            }

            $this->assertDoesNotMatchRegularExpression('#(^|/)effects(/|$)#', $uri, "Unexpected effects route: {$uri}");
        }
    }

    public function test_no_effect_osc_write_services_are_added_by_allocation_engine(): void
    {
        $this->assertFalse(class_exists(\App\Services\Console\ShowConsoleEffectControlService::class));
        $this->assertFalse(class_exists(\App\Services\X32\X32EffectOscWriter::class));
    }

    private function createSlotDefinition(
        string $slug,
        int $algorithmId,
        X32SlotGroup $slotGroup = X32SlotGroup::Fx1To4,
    ): EffectDefinition {
        return EffectDefinition::factory()->create([
            'slug' => $slug,
            'x32_algorithm_code' => 'DLY',
            'x32_algorithm_id' => $algorithmId,
            'x32_slot_group' => $slotGroup,
            'implementation_type' => EffectImplementationType::FxSlot,
        ]);
    }

    private function attachItem(
        EffectPackage $package,
        EffectDefinition $definition,
        bool $isRequired = true,
        ?int $preferredSlot = 1,
        int $priority = 10,
    ): void {
        EffectPackageItem::query()->create([
            'effect_package_id' => $package->id,
            'effect_definition_id' => $definition->id,
            'is_required' => $isRequired,
            'preferred_slot_number' => $preferredSlot,
            'slot_group_preference' => $definition->x32_slot_group->value,
            'priority' => $priority,
        ]);
    }

    private function assignPackage(Song $song, EffectPackage $package, int $priority = 100): void
    {
        SongEffectAssignment::factory()->create([
            'song_id' => $song->id,
            'effect_package_id' => $package->id,
            'priority' => $priority,
        ]);
    }
}
