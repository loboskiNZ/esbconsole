<?php

namespace Tests\Unit;

use App\Enums\X32SlotGroup;
use App\Models\EffectPackageItemParameter;
use App\Models\EffectPackageTypeOption;
use App\Models\X32Effect;
use App\Services\Effects\CreateEffectPackageService;
use App\Services\Effects\UpdateEffectPackageItemParameterService;
use Database\Seeders\EffectLibraryReferenceSeeder;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateEffectPackageItemParameterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectLibraryReferenceSeeder::class);
        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_updates_parameter_value(): void
    {
        $parameter = $this->createPackageParameter();

        $updated = app(UpdateEffectPackageItemParameterService::class)->update($parameter, '150');

        $this->assertSame('150', $updated->value);
        $this->assertDatabaseHas('effect_package_item_parameters', [
            'id' => $parameter->id,
            'value' => '150',
        ]);
    }

    public function test_null_value_clears_parameter(): void
    {
        $parameter = $this->createPackageParameter();

        app(UpdateEffectPackageItemParameterService::class)->update($parameter, null);

        $this->assertDatabaseHas('effect_package_item_parameters', [
            'id' => $parameter->id,
            'value' => null,
        ]);
    }

    public function test_rejects_invalid_enum_value(): void
    {
        $parameter = $this->createPackageParameter();
        $parameter->update([
            'value_type' => 'enum',
            'value' => 'OFF',
            'enum_values_json' => ['OFF', 'ON'],
            'min_value' => null,
            'max_value' => null,
        ]);

        $this->expectException(ValidationException::class);

        app(UpdateEffectPackageItemParameterService::class)->update($parameter->fresh(), 'MAYBE');
    }

    private function createPackageParameter(): EffectPackageItemParameter
    {
        $packageType = EffectPackageTypeOption::query()->firstOrFail();
        $plate = X32Effect::query()->where('effect_code', 'PLAT')->where('x32_slot_group', X32SlotGroup::Fx1To4)->firstOrFail();

        $package = app(CreateEffectPackageService::class)->create([
            'name' => 'Parameter Update Pack',
            'description' => null,
            'effect_package_type_id' => $packageType->id,
            'effect_ids' => [$plate->id],
        ]);

        return $package->effectPackageItems->firstOrFail()->parameters()->where('parameter_number', 1)->firstOrFail();
    }
}
