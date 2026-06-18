<?php

namespace Tests\Feature;

use App\Enums\EffectImplementationType;
use App\Enums\X32SlotGroup;
use App\Models\EffectPackage;
use App\Models\X32Effect;
use App\Models\X32EffectParameter;
use Database\Seeders\EffectLibraryReferenceSeeder;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EffectsAlgorithmReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_effects_table_has_operator_metadata_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('effects', 'operator_name'));
        $this->assertTrue(Schema::hasColumn('effects', 'operator_description'));
        $this->assertTrue(Schema::hasColumn('effects', 'recommended_for_json'));
        $this->assertTrue(Schema::hasColumn('effects', 'operator_category'));
        $this->assertTrue(Schema::hasColumn('effects', 'difficulty'));
        $this->assertTrue(Schema::hasColumn('effects', 'starter_notes'));
    }

    public function test_seeder_populates_operator_name_for_all_effects(): void
    {
        $this->assertSame(95, X32Effect::query()->count());
        $this->assertSame(
            95,
            X32Effect::query()->whereNotNull('operator_name')->where('operator_name', '!=', '')->count(),
        );
        $this->assertSame(
            95,
            X32Effect::query()->whereNotNull('operator_description')->where('operator_description', '!=', '')->count(),
        );
    }

    public function test_plat_displays_as_vocal_plate(): void
    {
        $this->assertOperatorName('PLAT', 'Vocal Plate');
    }

    public function test_dly_displays_as_vocal_delay(): void
    {
        $this->assertOperatorName('DLY', 'Vocal Delay');
    }

    public function test_modd_displays_as_dub_delay(): void
    {
        $this->assertOperatorName('MODD', 'Dub Delay');
    }

    public function test_crs_displays_as_voice_doubler(): void
    {
        $this->assertOperatorName('CRS', 'Voice Doubler');
    }

    public function test_filt_displays_as_radio_filter(): void
    {
        $this->assertOperatorName('FILT', 'Radio Filter');
    }

    public function test_wavd_displays_as_drum_punch(): void
    {
        $this->assertOperatorName('WAVD', 'Drum Punch');
    }

    public function test_geq_displays_as_graphic_eq(): void
    {
        $this->assertOperatorName('GEQ', 'Graphic EQ');
    }

    public function test_lim_displays_as_precision_limiter(): void
    {
        $this->assertOperatorName('LIM', 'Precision Limiter');
    }

    private function assertOperatorName(string $code, string $operatorName): void
    {
        $effect = X32Effect::query()
            ->where('effect_code', $code)
            ->where('x32_slot_group', X32SlotGroup::Fx1To4)
            ->firstOrFail();

        $this->assertSame($operatorName, $effect->operator_name);
    }

    public function test_geq_operator_name_is_shared_across_slot_groups(): void
    {
        $fx1 = X32Effect::query()->where('effect_code', 'GEQ')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();
        $fx5 = X32Effect::query()->where('effect_code', 'GEQ')->where('x32_slot_group', X32SlotGroup::Fx5To8)->firstOrFail();

        $this->assertSame('Graphic EQ', $fx1->operator_name);
        $this->assertSame('Graphic EQ', $fx5->operator_name);
        $this->assertNotSame($fx1->x32_algorithm_id, $fx5->x32_algorithm_id);
    }

    public function test_effects_table_contains_required_algorithms(): void
    {
        foreach (['HALL', 'PLAT', 'DLY', '4TAP', 'GEQ', 'LIM'] as $code) {
            $this->assertTrue(
                X32Effect::query()->where('effect_code', $code)->exists(),
                "Missing effect code {$code}",
            );
        }
    }

    public function test_effects_table_contains_slot_group_specific_rows(): void
    {
        $this->assertDatabaseHas('effects', [
            'effect_code' => 'GEQ',
            'x32_slot_group' => X32SlotGroup::Fx1To4->value,
            'x32_algorithm_id' => 28,
        ]);
        $this->assertDatabaseHas('effects', [
            'effect_code' => 'GEQ',
            'x32_slot_group' => X32SlotGroup::Fx5To8->value,
            'x32_algorithm_id' => 1,
        ]);
    }

    public function test_package_names_are_not_used_as_operator_names(): void
    {
        foreach ([
            'Standard Vocal/Horn',
            'FOH Main',
            'Reggae Dub',
            'Horn Funk',
            'Disco / Techno',
            'Vintage Radio Vocal',
        ] as $packageName) {
            $this->assertFalse(
                X32Effect::query()->where('operator_name', $packageName)->exists(),
                "Package name leaked into operator_name: {$packageName}",
            );
            $this->assertFalse(
                X32Effect::query()->where('effect_name', $packageName)->exists(),
                "Package name leaked into effect_name: {$packageName}",
            );
        }
    }

    public function test_effect_parameters_contain_verified_rows(): void
    {
        $hall = X32Effect::query()
            ->where('effect_code', 'HALL')
            ->where('x32_slot_group', X32SlotGroup::Fx1To4)
            ->firstOrFail();

        $plate = X32Effect::query()
            ->where('effect_code', 'PLAT')
            ->where('x32_slot_group', X32SlotGroup::Fx1To4)
            ->firstOrFail();

        $fourTap = X32Effect::query()
            ->where('effect_code', '4TAP')
            ->where('x32_slot_group', X32SlotGroup::Fx1To4)
            ->firstOrFail();

        $this->assertDatabaseHas('effect_parameters', [
            'effect_id' => $hall->id,
            'parameter_number' => 1,
            'parameter_name' => 'Pre Delay',
        ]);

        $this->assertDatabaseHas('effect_parameters', [
            'effect_id' => $plate->id,
            'parameter_number' => 2,
            'parameter_name' => 'Decay',
        ]);

        $this->assertDatabaseHas('effect_parameters', [
            'effect_id' => $fourTap->id,
            'parameter_number' => 1,
            'parameter_name' => 'Time',
        ]);
    }

    public function test_reference_seeder_loads_full_algorithm_catalogue_counts(): void
    {
        $this->assertSame(95, X32Effect::query()->count());
        $this->assertSame(61, X32Effect::query()->where('x32_slot_group', X32SlotGroup::Fx1To4)->count());
        $this->assertSame(34, X32Effect::query()->where('x32_slot_group', X32SlotGroup::Fx5To8)->count());
        $this->assertGreaterThan(150, X32EffectParameter::query()->count());
    }

    public function test_reference_seeder_does_not_create_packages(): void
    {
        $this->assertSame(0, EffectPackage::query()->count());
    }

    public function test_all_catalogue_effects_are_fx_slot_implementation(): void
    {
        $this->assertSame(
            0,
            X32Effect::query()
                ->where('implementation_type', '!=', EffectImplementationType::FxSlot)
                ->count(),
        );
    }
}
