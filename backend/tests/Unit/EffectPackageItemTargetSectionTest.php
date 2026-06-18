<?php

namespace Tests\Unit;

use App\Enums\EffectRoutingTargetSection;
use App\Enums\X32SlotGroup;
use App\Models\EffectPackageItemTargetSection;
use App\Models\EffectPackageTypeOption;
use App\Models\X32Effect;
use App\Services\Effects\CreateEffectPackageService;
use App\Services\Effects\EffectPackageItemTargetSectionSync;
use Database\Seeders\EffectLibraryReferenceSeeder;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EffectPackageItemTargetSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_target_sections_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('effect_package_item_target_sections'));
        $this->assertTrue(Schema::hasColumns('effect_package_item_target_sections', [
            'id',
            'effect_package_item_id',
            'target_section',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_package_item_can_have_multiple_target_sections(): void
    {
        $item = $this->createPackageWithEffects(['PLAT'])->effectPackageItems->firstOrFail();

        app(EffectPackageItemTargetSectionSync::class)->sync($item, [
            EffectRoutingTargetSection::Vocals,
            EffectRoutingTargetSection::Horns,
            EffectRoutingTargetSection::BackingVocals,
        ]);

        $this->assertCount(3, $item->fresh('targetSections')->targetSections);
        $this->assertSame(
            ['vocals', 'backing_vocals', 'horns'],
            $item->fresh('targetSections')->selectedTargetSectionValues(),
        );
    }

    public function test_duplicate_target_section_for_same_item_is_prevented_by_unique_constraint(): void
    {
        $item = $this->createPackageWithEffects(['PLAT'])->effectPackageItems->firstOrFail();

        EffectPackageItemTargetSection::query()->create([
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Vocals,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        EffectPackageItemTargetSection::query()->create([
            'effect_package_item_id' => $item->id,
            'target_section' => EffectRoutingTargetSection::Vocals,
        ]);
    }

    public function test_sync_rejects_invalid_target_section(): void
    {
        $item = $this->createPackageWithEffects(['PLAT'])->effectPackageItems->firstOrFail();

        $this->expectException(ValidationException::class);

        app(EffectPackageItemTargetSectionSync::class)->sync($item, ['not_a_section']);
    }

    public function test_sync_clears_target_sections_when_empty_list_submitted(): void
    {
        $item = $this->createPackageWithEffects(['PLAT'])->effectPackageItems->firstOrFail();

        app(EffectPackageItemTargetSectionSync::class)->sync($item, [EffectRoutingTargetSection::Vocals]);
        app(EffectPackageItemTargetSectionSync::class)->sync($item, []);

        $this->assertCount(0, $item->fresh('targetSections')->targetSections);
    }

    private function createPackageWithEffects(array $effectCodes)
    {
        $packageType = EffectPackageTypeOption::query()->firstOrFail();
        $effectIds = collect($effectCodes)
            ->map(fn (string $code) => X32Effect::query()
                ->where('effect_code', $code)
                ->where('x32_slot_group', X32SlotGroup::Fx1To4)
                ->firstOrFail()
                ->id)
            ->all();

        return app(CreateEffectPackageService::class)->create([
            'name' => 'Target Sections '.implode(' ', $effectCodes),
            'description' => null,
            'effect_package_type_id' => $packageType->id,
            'effect_ids' => $effectIds,
        ]);
    }
}
