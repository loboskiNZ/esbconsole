<?php

namespace Tests\Unit;

use App\Enums\EffectImplementationType;
use App\Enums\X32SlotGroup;
use App\Models\EffectPackage;
use App\Models\EffectPackageTypeOption;
use App\Models\X32Effect;
use App\Services\Effects\CreateEffectPackageService;
use App\Services\Effects\EffectPackageItemSlotAvailabilityService;
use App\Services\Effects\UpdateEffectPackageItemSlotService;
use Database\Seeders\EffectLibraryReferenceSeeder;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EffectPackageItemSlotAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_same_package_duplicate_slot_is_rejected(): void
    {
        $package = $this->createPackage(['PLAT', 'DLY']);
        $plateItem = $this->itemByCode($package, 'PLAT');
        $delayItem = $this->itemByCode($package, 'DLY');

        app(UpdateEffectPackageItemSlotService::class)->update($plateItem, 1);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('FX1 is already used by Vocal Plate in this package.');

        app(UpdateEffectPackageItemSlotService::class)->update($delayItem, 1);
    }

    public function test_permanent_package_slot_blocks_song_package_use(): void
    {
        $permanent = $this->createPackage(['GEQ'], 'FOH Main', EffectPackageTypeOption::SLUG_PERMANENT);
        $song = $this->createPackage(['GEQ'], 'Reggae Dub');
        $permanentItem = $permanent->effectPackageItems->firstOrFail();
        $songItem = $song->effectPackageItems->firstOrFail();

        app(UpdateEffectPackageItemSlotService::class)->update($permanentItem, 5);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('FX5 is reserved by permanent package FOH MAIN.');

        app(UpdateEffectPackageItemSlotService::class)->update($songItem, 5);
    }

    public function test_permanent_package_slot_blocks_special_treatment_use(): void
    {
        $permanent = $this->createPackage(['GEQ'], 'FOH Main', EffectPackageTypeOption::SLUG_PERMANENT);
        $special = $this->createPackage(['GEQ'], 'Vintage Radio', EffectPackageTypeOption::SLUG_SPECIAL_TREATMENT);
        $permanentItem = $permanent->effectPackageItems->firstOrFail();
        $specialItem = $special->effectPackageItems->firstOrFail();

        app(UpdateEffectPackageItemSlotService::class)->update($permanentItem, 5);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('FX5 is reserved by permanent package FOH MAIN.');

        app(UpdateEffectPackageItemSlotService::class)->update($specialItem, 5);
    }

    public function test_permanent_package_slot_blocks_another_permanent_package_use(): void
    {
        $fohMain = $this->createPackage(['GEQ'], 'FOH Main', EffectPackageTypeOption::SLUG_PERMANENT);
        $vocalHorn = $this->createPackage(['LIM'], 'Standard Vocal Horn', EffectPackageTypeOption::SLUG_PERMANENT);
        $fohItem = $fohMain->effectPackageItems->firstOrFail();
        $limiterItem = $vocalHorn->effectPackageItems->firstOrFail();

        app(UpdateEffectPackageItemSlotService::class)->update($fohItem, 5);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('FX5 is reserved by permanent package FOH MAIN.');

        app(UpdateEffectPackageItemSlotService::class)->update($limiterItem, 5);
    }

    public function test_song_package_can_reuse_slot_used_by_another_song_package(): void
    {
        $packageA = $this->createPackage(['PLAT'], 'Reggae Dub');
        $packageB = $this->createPackage(['DLY'], 'Disco Techno');
        $itemA = $packageA->effectPackageItems->firstOrFail();
        $itemB = $packageB->effectPackageItems->firstOrFail();

        app(UpdateEffectPackageItemSlotService::class)->update($itemA, 3);
        $updated = app(UpdateEffectPackageItemSlotService::class)->update($itemB, 3);

        $this->assertSame(3, $updated->preferred_slot_number);
    }

    public function test_special_treatment_can_reuse_slot_used_by_song_package(): void
    {
        $song = $this->createPackage(['DLY'], 'Reggae Dub');
        $special = $this->createPackage(['PLAT'], 'Vintage Radio', EffectPackageTypeOption::SLUG_SPECIAL_TREATMENT);
        $songItem = $song->effectPackageItems->firstOrFail();
        $specialItem = $special->effectPackageItems->firstOrFail();

        app(UpdateEffectPackageItemSlotService::class)->update($songItem, 3);
        $updated = app(UpdateEffectPackageItemSlotService::class)->update($specialItem, 3);

        $this->assertSame(3, $updated->preferred_slot_number);
    }

    public function test_different_special_treatments_can_reuse_same_slot(): void
    {
        $specialA = $this->createPackage(['PLAT'], 'Vintage Radio', EffectPackageTypeOption::SLUG_SPECIAL_TREATMENT);
        $specialB = $this->createPackage(['DLY'], 'Lo-Fi Vocal', EffectPackageTypeOption::SLUG_SPECIAL_TREATMENT);
        $itemA = $specialA->effectPackageItems->firstOrFail();
        $itemB = $specialB->effectPackageItems->firstOrFail();

        app(UpdateEffectPackageItemSlotService::class)->update($itemA, 4);
        $updated = app(UpdateEffectPackageItemSlotService::class)->update($itemB, 4);

        $this->assertSame(4, $updated->preferred_slot_number);
    }

    public function test_current_package_can_keep_existing_allocated_slot(): void
    {
        $package = $this->createPackage(['PLAT', 'DLY']);
        $plateItem = $this->itemByCode($package, 'PLAT');

        app(UpdateEffectPackageItemSlotService::class)->update($plateItem, 2);
        $updated = app(UpdateEffectPackageItemSlotService::class)->update($plateItem->fresh(), 2);

        $this->assertSame(2, $updated->preferred_slot_number);
        $this->assertNull(app(EffectPackageItemSlotAvailabilityService::class)->reasonForSlot($plateItem->fresh(), 2));
    }

    public function test_slot_group_rules_still_apply(): void
    {
        $item = $this->createPackage(['PLAT'])->effectPackageItems->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Vocal Plate can only use FX1–FX4.');

        app(UpdateEffectPackageItemSlotService::class)->update($item, 6);
    }

    /**
     * @param  list<string>  $effectCodes
     */
    private function createPackage(array $effectCodes, string $name = 'Slot Pack', string $typeSlug = EffectPackageTypeOption::SLUG_SONG_PACKAGE): EffectPackage
    {
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

    private function itemByCode(EffectPackage $package, string $code)
    {
        return $package->effectPackageItems->firstWhere(fn ($item) => $item->x32Effect?->effect_code === $code);
    }
}
