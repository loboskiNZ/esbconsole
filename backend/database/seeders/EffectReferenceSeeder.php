<?php

namespace Database\Seeders;

use App\Enums\EffectActiveSongSafety;
use App\Enums\EffectImplementationType;
use App\Enums\EffectPackageType;
use App\Enums\EffectTempoBehavior;
use App\Enums\X32SlotGroup;
use App\Models\EffectDefinition;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use App\Models\EffectPackageTypeOption;
use Illuminate\Database\Seeder;

/**
 * PH044 reference catalogue — musical effect definitions and packages.
 * Not demo/song data; safe to run in all environments.
 */
class EffectReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EffectLibraryReferenceSeeder::class);
        $this->call(EffectsAlgorithmReferenceSeeder::class);

        $definitions = $this->seedDefinitions();
        $this->seedPackages($definitions);
    }

    /**
     * @return array<string, EffectDefinition>
     */
    private function seedDefinitions(): array
    {
        $rows = [
            [
                'slug' => 'shared-vocal-horn-plate',
                'name' => 'Shared Vocal/Horn Plate',
                'category' => EffectDefinition::CATEGORY_PLATE,
                'target_section' => EffectDefinition::TARGET_VOCAL,
                'x32_algorithm_code' => 'PLAT',
                'x32_algorithm_id' => 5,
                'x32_slot_group' => X32SlotGroup::Fx1To4,
                'effect_role' => EffectDefinition::ROLE_AMBIENCE,
                'implementation_type' => EffectImplementationType::FxSlot,
                'tempo_behavior' => EffectTempoBehavior::TempoNeutral,
                'active_song_safety' => EffectActiveSongSafety::BetweenSongOnly,
                'notes' => 'Maillot FX1–4 enum 5. Algorithm assignment between songs only.',
            ],
            [
                'slug' => 'shared-vocal-horn-delay',
                'name' => 'Shared Vocal/Horn Delay',
                'category' => EffectDefinition::CATEGORY_DELAY,
                'target_section' => EffectDefinition::TARGET_VOCAL,
                'x32_algorithm_code' => 'DLY',
                'x32_algorithm_id' => 10,
                'x32_slot_group' => X32SlotGroup::Fx1To4,
                'effect_role' => EffectDefinition::ROLE_DELAY,
                'implementation_type' => EffectImplementationType::FxSlot,
                'tempo_behavior' => EffectTempoBehavior::MusicalTimeAware,
                'active_song_safety' => EffectActiveSongSafety::SafeDuringSong,
            ],
            [
                'slug' => 'foh-graphic-eq',
                'name' => 'FOH Graphic EQ',
                'category' => EffectDefinition::CATEGORY_GRAPHIC_EQ,
                'target_section' => EffectDefinition::TARGET_FOH,
                'x32_algorithm_code' => 'GEQ',
                'x32_algorithm_id' => 1,
                'x32_slot_group' => X32SlotGroup::Fx5To8,
                'effect_role' => EffectDefinition::ROLE_PROCESSOR,
                'implementation_type' => EffectImplementationType::Hybrid,
                'tempo_behavior' => EffectTempoBehavior::TempoNeutral,
                'active_song_safety' => EffectActiveSongSafety::SafeDuringSong,
                'notes' => 'Maillot FX5–8 enum 1. May deploy to FX slot or complement Main ST EQ.',
            ],
            [
                'slug' => 'foh-limiter-compressor',
                'name' => 'FOH Limiter/Compressor',
                'category' => EffectDefinition::CATEGORY_LIMITER,
                'target_section' => EffectDefinition::TARGET_FOH,
                'x32_algorithm_code' => 'LIM',
                'x32_algorithm_id' => 11,
                'x32_slot_group' => X32SlotGroup::Fx5To8,
                'effect_role' => EffectDefinition::ROLE_PROCESSOR,
                'implementation_type' => EffectImplementationType::MainProcessing,
                'tempo_behavior' => EffectTempoBehavior::TempoNeutral,
                'active_song_safety' => EffectActiveSongSafety::SafeDuringSong,
                'notes' => 'Maillot FX5–8 enum 11 (LIM). Main dynamics may also apply.',
            ],
            [
                'slug' => 'reggae-dub-delay',
                'name' => 'Reggae Dub Delay',
                'category' => EffectDefinition::CATEGORY_DUB_DELAY,
                'target_section' => EffectDefinition::TARGET_SPECIAL,
                'x32_algorithm_code' => 'MODD',
                'x32_algorithm_id' => 26,
                'x32_slot_group' => X32SlotGroup::Fx1To4,
                'effect_role' => EffectDefinition::ROLE_DELAY,
                'implementation_type' => EffectImplementationType::FxSlot,
                'tempo_behavior' => EffectTempoBehavior::TempoAware,
                'active_song_safety' => EffectActiveSongSafety::SafeDuringSong,
                'notes' => 'Maillot FX1–4 enum 26. Tempo/musical-time rules deferred to future layer.',
            ],
            [
                'slug' => 'horn-funk-reverb',
                'name' => 'Horn Funk Reverb',
                'category' => EffectDefinition::CATEGORY_REVERB,
                'target_section' => EffectDefinition::TARGET_HORN,
                'x32_algorithm_code' => 'ROOM',
                'x32_algorithm_id' => 3,
                'x32_slot_group' => X32SlotGroup::Fx1To4,
                'effect_role' => EffectDefinition::ROLE_AMBIENCE,
                'implementation_type' => EffectImplementationType::FxSlot,
                'tempo_behavior' => EffectTempoBehavior::TempoNeutral,
                'active_song_safety' => EffectActiveSongSafety::BetweenSongOnly,
            ],
            [
                'slug' => 'disco-chorus',
                'name' => 'Disco Chorus',
                'category' => EffectDefinition::CATEGORY_CHORUS,
                'target_section' => EffectDefinition::TARGET_SPECIAL,
                'x32_algorithm_code' => 'CRS',
                'x32_algorithm_id' => 13,
                'x32_slot_group' => X32SlotGroup::Fx1To4,
                'effect_role' => EffectDefinition::ROLE_SPECIAL_TREATMENT,
                'implementation_type' => EffectImplementationType::FxSlot,
                'tempo_behavior' => EffectTempoBehavior::TempoNeutral,
                'active_song_safety' => EffectActiveSongSafety::SafeDuringSong,
            ],
            [
                'slug' => 'vintage-radio-vocal-filter',
                'name' => 'Vintage Radio Vocal Filter',
                'category' => EffectDefinition::CATEGORY_SPECIAL_FX,
                'target_section' => EffectDefinition::TARGET_VOCAL,
                'x32_algorithm_code' => 'FILT',
                'x32_algorithm_id' => 17,
                'x32_slot_group' => X32SlotGroup::Fx1To4,
                'effect_role' => EffectDefinition::ROLE_SPECIAL_TREATMENT,
                'implementation_type' => EffectImplementationType::FxSlot,
                'tempo_behavior' => EffectTempoBehavior::TempoNeutral,
                'active_song_safety' => EffectActiveSongSafety::SafeDuringSong,
                'notes' => 'Maillot FX1–4 enum 17. Full vintage chain may add channel EQ — not modelled yet.',
            ],
            [
                'slug' => 'vintage-radio-vocal-deesser',
                'name' => 'Vintage Radio Vocal De-Esser',
                'category' => EffectDefinition::CATEGORY_ENHANCER,
                'target_section' => EffectDefinition::TARGET_VOCAL,
                'x32_algorithm_code' => 'DES',
                'x32_algorithm_id' => 5,
                'x32_slot_group' => X32SlotGroup::Fx5To8,
                'effect_role' => EffectDefinition::ROLE_PROCESSOR,
                'implementation_type' => EffectImplementationType::FxSlot,
                'tempo_behavior' => EffectTempoBehavior::TempoNeutral,
                'active_song_safety' => EffectActiveSongSafety::SafeDuringSong,
                'notes' => 'Maillot FX5–8 enum 5. Optional companion to vintage radio package.',
            ],
        ];

        $definitions = [];

        foreach ($rows as $row) {
            $definitions[$row['slug']] = EffectDefinition::query()->updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['is_active' => true]),
            );
        }

        return $definitions;
    }

    /**
     * @param  array<string, EffectDefinition>  $definitions
     */
    private function seedPackages(array $definitions): void
    {
        $packages = [
            'standard-vocal-horn-package' => [
                'name' => 'Standard Vocal/Horn Package',
                'package_type' => EffectPackageType::Permanent,
                'target_section' => EffectPackage::TARGET_VOCAL,
                'priority' => 10,
                'description' => 'Base vocal and horn ambience shared across the show.',
                'is_default' => true,
                'items' => [
                    ['slug' => 'shared-vocal-horn-plate', 'preferred_slot_number' => 1, 'priority' => 10, 'is_required' => true],
                    ['slug' => 'shared-vocal-horn-delay', 'preferred_slot_number' => 2, 'priority' => 20, 'is_required' => true],
                ],
            ],
            'foh-main-package' => [
                'name' => 'FOH Main Package',
                'package_type' => EffectPackageType::Permanent,
                'target_section' => EffectPackage::TARGET_FOH,
                'priority' => 5,
                'description' => 'House processing for Main LR.',
                'is_default' => true,
                'items' => [
                    ['slug' => 'foh-graphic-eq', 'preferred_slot_number' => 5, 'priority' => 10, 'is_required' => true],
                    ['slug' => 'foh-limiter-compressor', 'preferred_slot_number' => 6, 'priority' => 20, 'is_required' => true],
                ],
            ],
            'reggae-dub-package' => [
                'name' => 'Reggae Dub Package',
                'package_type' => EffectPackageType::SongSelectable,
                'target_section' => EffectPackage::TARGET_SPECIAL,
                'priority' => 50,
                'description' => 'Modulation delay and dub ambience for reggae selections.',
                'is_default' => false,
                'items' => [
                    ['slug' => 'reggae-dub-delay', 'preferred_slot_number' => 3, 'priority' => 10, 'is_required' => true],
                    ['slug' => 'shared-vocal-horn-delay', 'preferred_slot_number' => 2, 'priority' => 20, 'is_required' => false],
                ],
            ],
            'horn-funk-package' => [
                'name' => 'Horn Funk Package',
                'package_type' => EffectPackageType::SongSelectable,
                'target_section' => EffectPackage::TARGET_HORN,
                'priority' => 60,
                'description' => 'Room verb and horn-friendly ambience.',
                'is_default' => false,
                'items' => [
                    ['slug' => 'horn-funk-reverb', 'preferred_slot_number' => 1, 'priority' => 10, 'is_required' => true],
                ],
            ],
            'disco-techno-package' => [
                'name' => 'Disco / Techno Package',
                'package_type' => EffectPackageType::SongSelectable,
                'target_section' => EffectPackage::TARGET_SPECIAL,
                'priority' => 70,
                'description' => 'Chorus and delay accents for dance selections.',
                'is_default' => false,
                'items' => [
                    ['slug' => 'disco-chorus', 'preferred_slot_number' => 3, 'priority' => 10, 'is_required' => true],
                    ['slug' => 'shared-vocal-horn-delay', 'preferred_slot_number' => 2, 'priority' => 20, 'is_required' => false],
                ],
            ],
            'vintage-radio-vocal' => [
                'name' => 'Vintage Radio Vocal',
                'package_type' => EffectPackageType::SpecialTreatment,
                'target_section' => EffectPackage::TARGET_SPECIAL,
                'priority' => 80,
                'description' => 'Lo-fi vocal treatment for vintage radio aesthetic.',
                'is_default' => false,
                'items' => [
                    ['slug' => 'vintage-radio-vocal-filter', 'preferred_slot_number' => 4, 'priority' => 10, 'is_required' => true],
                    ['slug' => 'vintage-radio-vocal-deesser', 'preferred_slot_number' => 7, 'priority' => 20, 'is_required' => false],
                ],
            ],
        ];

        foreach ($packages as $slug => $packageRow) {
            $typeOption = EffectPackageTypeOption::query()
                ->where('slug', match ($packageRow['package_type']) {
                    EffectPackageType::Permanent => EffectPackageTypeOption::SLUG_PERMANENT,
                    EffectPackageType::SongSelectable => EffectPackageTypeOption::SLUG_SONG_PACKAGE,
                    EffectPackageType::SpecialTreatment => EffectPackageTypeOption::SLUG_SPECIAL_TREATMENT,
                })
                ->first();

            $package = EffectPackage::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $packageRow['name'],
                    'effect_package_type_id' => $typeOption?->id,
                    'package_type' => $packageRow['package_type'],
                    'target_section' => $packageRow['target_section'],
                    'priority' => $packageRow['priority'],
                    'description' => $packageRow['description'],
                    'is_default' => $packageRow['is_default'],
                    'is_active' => true,
                ],
            );

            foreach ($packageRow['items'] as $itemRow) {
                $definition = $definitions[$itemRow['slug']];

                EffectPackageItem::query()->updateOrCreate(
                    [
                        'effect_package_id' => $package->id,
                        'effect_definition_id' => $definition->id,
                    ],
                    [
                        'is_required' => $itemRow['is_required'],
                        'preferred_slot_number' => $itemRow['preferred_slot_number'],
                        'slot_group_preference' => $definition->x32_slot_group->value,
                        'priority' => $itemRow['priority'],
                        'parameter_overrides_json' => null,
                        'timing_rules_json' => null,
                    ],
                );
            }
        }
    }
}
