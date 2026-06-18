<?php

namespace Database\Factories;

use App\Enums\EffectPackageType;
use App\Models\EffectPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EffectPackage>
 */
class EffectPackageFactory extends Factory
{
    protected $model = EffectPackage::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'package_type' => EffectPackageType::SongSelectable,
            'target_section' => EffectPackage::TARGET_VOCAL,
            'priority' => 100,
            'description' => null,
            'is_default' => false,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function permanent(): static
    {
        return $this->state(fn (): array => [
            'package_type' => EffectPackageType::Permanent,
            'is_default' => true,
        ]);
    }

    public function specialTreatment(): static
    {
        return $this->state(fn (): array => [
            'package_type' => EffectPackageType::SpecialTreatment,
            'target_section' => EffectPackage::TARGET_SPECIAL,
        ]);
    }
}
