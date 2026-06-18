<?php

namespace App\Services\Effects;

use App\Enums\EffectRoutingTargetSection;
use App\Models\EffectPackageItem;
use App\Models\EffectPackageItemTargetSection;
use Illuminate\Validation\ValidationException;

class EffectPackageItemTargetSectionSync
{
    /**
     * @param  list<string>|list<EffectRoutingTargetSection>  $sections
     */
    public function sync(EffectPackageItem $item, array $sections): void
    {
        $normalized = collect($sections)
            ->map(function ($section) {
                if ($section instanceof EffectRoutingTargetSection) {
                    return $section;
                }

                $enum = EffectRoutingTargetSection::tryFrom((string) $section);

                if ($enum === null || $enum === EffectRoutingTargetSection::NotConfigured) {
                    throw ValidationException::withMessages([
                        'target_sections' => 'One or more target sections are not valid.',
                    ]);
                }

                return $enum;
            })
            ->unique(fn (EffectRoutingTargetSection $section) => $section->value)
            ->values();

        $this->assertNoDuplicates($sections);

        $item->targetSections()->delete();

        foreach ($normalized as $section) {
            EffectPackageItemTargetSection::query()->create([
                'effect_package_item_id' => $item->id,
                'target_section' => $section,
            ]);
        }
    }

    /**
     * @param  list<EffectRoutingTargetSection>  $sections
     */
    public function syncSuggested(EffectPackageItem $item, array $sections): void
    {
        $this->sync($item, $sections);
    }

    /**
     * @param  list<string>|list<EffectRoutingTargetSection>  $sections
     */
    private function assertNoDuplicates(array $sections): void
    {
        $values = collect($sections)->map(function ($section) {
            return $section instanceof EffectRoutingTargetSection ? $section->value : (string) $section;
        });

        if ($values->count() !== $values->unique()->count()) {
            throw ValidationException::withMessages([
                'target_sections' => 'Duplicate target sections are not allowed.',
            ]);
        }
    }
}
