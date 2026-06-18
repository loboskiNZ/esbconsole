<?php

namespace Tests\Unit;

use App\Enums\EffectImplementationType;
use App\Enums\X32SlotGroup;
use App\Models\EffectPackageTypeOption;
use App\Models\X32Effect;
use App\Services\Effects\CreateEffectPackageService;
use App\Services\Effects\UpdateEffectPackageItemSlotService;
use Database\Seeders\EffectLibraryReferenceSeeder;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateEffectPackageItemSlotServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_saves_valid_slot_for_fx1_4_effect(): void
    {
        $item = $this->createPackageWithEffects(['PLAT'])->effectPackageItems->firstOrFail();

        $updated = app(UpdateEffectPackageItemSlotService::class)->update($item, 2);

        $this->assertSame(2, $updated->preferred_slot_number);
    }

    public function test_clears_slot_when_null(): void
    {
        $item = $this->createPackageWithEffects(['PLAT'])->effectPackageItems->firstOrFail();
        $item->update(['preferred_slot_number' => 1]);

        app(UpdateEffectPackageItemSlotService::class)->update($item->fresh(), null);

        $this->assertNull($item->fresh()->preferred_slot_number);
    }

    public function test_rejects_slot_outside_fx1_4_group(): void
    {
        $item = $this->createPackageWithEffects(['PLAT'])->effectPackageItems->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Vocal Plate can only use FX1–FX4.');

        app(UpdateEffectPackageItemSlotService::class)->update($item, 5);
    }

    public function test_rejects_slot_outside_fx5_8_group(): void
    {
        $item = $this->createPackageWithEffects(['GEQ'])->effectPackageItems->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Graphic EQ can only use FX5–FX8.');

        app(UpdateEffectPackageItemSlotService::class)->update($item, 2);
    }

    public function test_rejects_duplicate_slot_in_same_package(): void
    {
        $package = $this->createPackageWithEffects(['PLAT', 'DLY']);
        $plateItem = $package->effectPackageItems->firstWhere(fn ($item) => $item->x32Effect?->effect_code === 'PLAT');
        $delayItem = $package->effectPackageItems->firstWhere(fn ($item) => $item->x32Effect?->effect_code === 'DLY');

        app(UpdateEffectPackageItemSlotService::class)->update($plateItem, 1);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('FX1 is already used by Vocal Plate in this package.');

        app(UpdateEffectPackageItemSlotService::class)->update($delayItem, 1);
    }

    public function test_allows_same_slot_in_different_packages(): void
    {
        $packageA = $this->createPackageWithEffects(['PLAT'], 'Package A');
        $packageB = $this->createPackageWithEffects(['PLAT'], 'Package B');

        $itemA = $packageA->effectPackageItems->firstOrFail();
        $itemB = $packageB->effectPackageItems->firstOrFail();

        app(UpdateEffectPackageItemSlotService::class)->update($itemA, 1);
        $updatedB = app(UpdateEffectPackageItemSlotService::class)->update($itemB, 1);

        $this->assertSame(1, $updatedB->preferred_slot_number);
    }

    public function test_allows_any_slot_group_effect_to_use_fx1_through_fx8(): void
    {
        $effect = X32Effect::query()->create([
            'effect_code' => 'ANYX',
            'effect_name' => 'Any Slot Effect',
            'operator_name' => 'Any Slot Effect',
            'x32_algorithm_id' => 98,
            'x32_slot_group' => X32SlotGroup::Any,
            'category' => 'delay',
            'implementation_type' => EffectImplementationType::FxSlot,
            'is_active' => true,
        ]);

        $package = $this->createPackageWithEffects([], 'Any Slot Pack', [$effect->id]);
        $item = $package->effectPackageItems->firstOrFail();

        $updated = app(UpdateEffectPackageItemSlotService::class)->update($item, 8);

        $this->assertSame(8, $updated->preferred_slot_number);
    }

    /**
     * @param  list<string>  $effectCodes
     * @param  list<int>  $effectIds
     */
    private function createPackageWithEffects(array $effectCodes, string $name = 'Slot Pack', array $effectIds = [])
    {
        $packageType = EffectPackageTypeOption::query()->where('slug', EffectPackageTypeOption::SLUG_SONG_PACKAGE)->firstOrFail();

        if ($effectIds === []) {
            $effectIds = collect($effectCodes)
                ->map(fn (string $code) => X32Effect::query()
                    ->where('effect_code', $code)
                    ->where('x32_slot_group', $code === 'GEQ' ? X32SlotGroup::Fx5To8 : X32SlotGroup::Fx1To4)
                    ->firstOrFail()
                    ->id)
                ->all();
        }

        return app(CreateEffectPackageService::class)->create([
            'name' => $name,
            'description' => null,
            'effect_package_type_id' => $packageType->id,
            'effect_ids' => $effectIds,
        ]);
    }
}
