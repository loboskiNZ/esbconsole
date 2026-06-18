<?php

namespace Tests\Feature;

use App\Enums\EffectActiveSongSafety;
use App\Enums\EffectImplementationType;
use App\Enums\EffectPackageType;
use App\Enums\EffectTempoBehavior;
use App\Enums\FallbackConsoleRecallType;
use App\Enums\SongEffectAssignmentType;
use App\Enums\X32SlotGroup;
use App\Models\EffectDefinition;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use App\Models\Song;
use App\Models\SongEffectAssignment;
use Database\Seeders\EffectReferenceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EffectDomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_effect_definitions_can_be_created_with_verified_algorithm_identity(): void
    {
        $definition = EffectDefinition::factory()->create([
            'slug' => 'verified-plate',
            'x32_algorithm_code' => 'PLAT',
            'x32_algorithm_id' => 5,
            'x32_slot_group' => X32SlotGroup::Fx1To4,
        ]);

        $this->assertSame('PLAT', $definition->fresh()->x32_algorithm_code);
        $this->assertSame(5, $definition->fresh()->x32_algorithm_id);
        $this->assertSame(X32SlotGroup::Fx1To4, $definition->fresh()->x32_slot_group);
    }

    public function test_effect_definitions_can_be_created_with_unknown_algorithm_identity(): void
    {
        $definition = EffectDefinition::factory()->unknownAlgorithmIdentity()->create([
            'slug' => 'unverified-future-fx',
        ]);

        $this->assertNull($definition->fresh()->x32_algorithm_code);
        $this->assertNull($definition->fresh()->x32_algorithm_id);
        $this->assertSame(EffectActiveSongSafety::Unknown, $definition->fresh()->active_song_safety);
    }

    public function test_effect_packages_can_be_created(): void
    {
        $package = EffectPackage::factory()->create([
            'slug' => 'test-package',
            'package_type' => EffectPackageType::SongSelectable,
        ]);

        $this->assertSame(EffectPackageType::SongSelectable, $package->fresh()->package_type);
        $this->assertTrue($package->fresh()->is_active);
    }

    public function test_packages_can_contain_multiple_effect_definitions(): void
    {
        $package = EffectPackage::factory()->create();
        $plate = EffectDefinition::factory()->create(['slug' => 'pkg-plate']);
        $delay = EffectDefinition::factory()->create(['slug' => 'pkg-delay']);

        EffectPackageItem::query()->create([
            'effect_package_id' => $package->id,
            'effect_definition_id' => $plate->id,
            'priority' => 10,
        ]);
        EffectPackageItem::query()->create([
            'effect_package_id' => $package->id,
            'effect_definition_id' => $delay->id,
            'priority' => 20,
        ]);

        $this->assertCount(2, $package->fresh()->effectDefinitions);
        $this->assertTrue($package->fresh()->effectDefinitions->first()->is($plate));
    }

    public function test_effect_definition_can_belong_to_multiple_packages(): void
    {
        $definition = EffectDefinition::factory()->create(['slug' => 'shared-delay']);
        $packageA = EffectPackage::factory()->create(['slug' => 'package-a']);
        $packageB = EffectPackage::factory()->create(['slug' => 'package-b']);

        EffectPackageItem::query()->create([
            'effect_package_id' => $packageA->id,
            'effect_definition_id' => $definition->id,
        ]);
        EffectPackageItem::query()->create([
            'effect_package_id' => $packageB->id,
            'effect_definition_id' => $definition->id,
        ]);

        $this->assertCount(2, $definition->fresh()->effectPackages);
    }

    public function test_songs_can_be_assigned_effect_packages(): void
    {
        $song = Song::factory()->create(['song_code' => '201']);
        $package = EffectPackage::factory()->create(['slug' => 'assigned-package']);

        SongEffectAssignment::factory()->create([
            'song_id' => $song->id,
            'effect_package_id' => $package->id,
            'assignment_type' => SongEffectAssignmentType::SongSpecific,
        ]);

        $this->assertCount(1, $song->fresh()->effectPackages);
        $this->assertTrue($song->fresh()->effectPackages->first()->is($package));
        $this->assertSame(
            SongEffectAssignmentType::SongSpecific,
            $song->fresh()->songEffectAssignments->first()->assignment_type,
        );
    }

    public function test_duplicate_package_effect_membership_is_prevented(): void
    {
        $package = EffectPackage::factory()->create();
        $definition = EffectDefinition::factory()->create();

        EffectPackageItem::query()->create([
            'effect_package_id' => $package->id,
            'effect_definition_id' => $definition->id,
        ]);

        $this->expectException(QueryException::class);
        EffectPackageItem::query()->create([
            'effect_package_id' => $package->id,
            'effect_definition_id' => $definition->id,
        ]);
    }

    public function test_duplicate_song_package_assignment_is_prevented(): void
    {
        $song = Song::factory()->create(['song_code' => '202']);
        $package = EffectPackage::factory()->create();

        SongEffectAssignment::factory()->create([
            'song_id' => $song->id,
            'effect_package_id' => $package->id,
        ]);

        $this->expectException(QueryException::class);
        SongEffectAssignment::factory()->create([
            'song_id' => $song->id,
            'effect_package_id' => $package->id,
        ]);
    }

    public function test_fallback_console_recall_metadata_can_be_stored(): void
    {
        $assignment = SongEffectAssignment::factory()
            ->withFallbackRecall('Dub Scene A', FallbackConsoleRecallType::Scene)
            ->create();

        $fresh = $assignment->fresh();

        $this->assertSame('Dub Scene A', $fresh->fallback_console_recall_name);
        $this->assertSame(FallbackConsoleRecallType::Scene, $fresh->fallback_console_recall_type);
    }

    public function test_permanent_and_song_selectable_packages_are_distinguishable(): void
    {
        $permanent = EffectPackage::factory()->permanent()->create(['slug' => 'permanent-pkg']);
        $selectable = EffectPackage::factory()->create([
            'slug' => 'selectable-pkg',
            'package_type' => EffectPackageType::SongSelectable,
        ]);

        $this->assertSame(EffectPackageType::Permanent, $permanent->fresh()->package_type);
        $this->assertTrue($permanent->fresh()->is_default);
        $this->assertSame(EffectPackageType::SongSelectable, $selectable->fresh()->package_type);
        $this->assertFalse($selectable->fresh()->is_default);
    }

    public function test_tempo_and_musical_time_metadata_can_be_stored_without_clock_integration(): void
    {
        $definition = EffectDefinition::factory()->create([
            'slug' => 'tempo-delay',
            'tempo_behavior' => EffectTempoBehavior::TempoAware,
        ]);

        $package = EffectPackage::factory()->create();
        EffectPackageItem::query()->create([
            'effect_package_id' => $package->id,
            'effect_definition_id' => $definition->id,
            'timing_rules_json' => [
                'sync' => 'quarter_note',
                'delay_division' => 'dotted_eighth',
            ],
        ]);

        $this->assertSame(EffectTempoBehavior::TempoAware, $definition->fresh()->tempo_behavior);
        $this->assertSame(
            'dotted_eighth',
            $package->fresh()->effectPackageItems->first()->timing_rules_json['delay_division'],
        );
    }

    public function test_reference_seeder_creates_required_packages_without_demo_song_assignments(): void
    {
        $this->seed(EffectReferenceSeeder::class);

        $this->assertDatabaseHas('effect_packages', ['slug' => 'standard-vocal-horn-package']);
        $this->assertDatabaseHas('effect_packages', ['slug' => 'foh-main-package']);
        $this->assertDatabaseHas('effect_packages', ['slug' => 'reggae-dub-package']);
        $this->assertDatabaseHas('effect_packages', ['slug' => 'horn-funk-package']);
        $this->assertDatabaseHas('effect_packages', ['slug' => 'disco-techno-package']);
        $this->assertDatabaseHas('effect_packages', ['slug' => 'vintage-radio-vocal']);
        $this->assertDatabaseHas('effect_definitions', ['slug' => 'shared-vocal-horn-plate', 'x32_algorithm_code' => 'PLAT']);
        $this->assertSame(0, SongEffectAssignment::query()->count());
    }

    public function test_no_effects_live_control_routes_are_registered(): void
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

    public function test_no_effect_osc_write_services_exist(): void
    {
        $this->assertFalse(class_exists(\App\Services\Console\ShowConsoleEffectControlService::class));
        $this->assertFalse(class_exists(\App\Services\X32\X32EffectOscWriter::class));
    }
}
