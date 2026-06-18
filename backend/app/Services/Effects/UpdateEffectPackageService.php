<?php

namespace App\Services\Effects;

use App\Models\EffectPackage;
use App\Models\EffectPackageTypeOption;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateEffectPackageService
{
    /**
     * @param  array{
     *     name: string,
     *     description: ?string,
     *     effect_package_type_id: int,
     *     is_active?: bool
     * } $data
     */
    public function update(EffectPackage $package, array $data): EffectPackage
    {
        $packageType = EffectPackageTypeOption::query()
            ->where('is_active', true)
            ->find($data['effect_package_type_id']);

        if ($packageType === null) {
            throw ValidationException::withMessages([
                'effect_package_type_id' => 'Selected package type is not valid.',
            ]);
        }

        $name = Str::upper(trim($data['name']));

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Package name is required.',
            ]);
        }

        $slug = $this->uniqueSlug($name, $package->id);

        $package->update([
            'name' => $name,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'effect_package_type_id' => $packageType->id,
            'package_type' => $packageType->toPackageTypeEnum(),
            'is_active' => (bool) ($data['is_active'] ?? $package->is_active),
        ]);

        return $package->fresh(['effectPackageTypeOption']);
    }

    private function uniqueSlug(string $name, int $ignorePackageId): string
    {
        $base = Str::slug(Str::lower($name));
        $base = $base !== '' ? $base : 'effect-package';
        $slug = $base;
        $suffix = 2;

        while (EffectPackage::query()
            ->where('slug', $slug)
            ->where('id', '!=', $ignorePackageId)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
