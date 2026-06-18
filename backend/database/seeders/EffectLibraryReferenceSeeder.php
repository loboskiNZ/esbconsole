<?php

namespace Database\Seeders;

use App\Models\EffectPackageTypeOption;
use Illuminate\Database\Seeder;

/**
 * PH044 package type reference records.
 */
class EffectLibraryReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Permanent', 'slug' => EffectPackageTypeOption::SLUG_PERMANENT, 'display_order' => 10],
            ['name' => 'Song Package', 'slug' => EffectPackageTypeOption::SLUG_SONG_PACKAGE, 'display_order' => 20],
            ['name' => 'Special Treatment', 'slug' => EffectPackageTypeOption::SLUG_SPECIAL_TREATMENT, 'display_order' => 30],
        ];

        foreach ($types as $type) {
            EffectPackageTypeOption::query()->updateOrCreate(
                ['slug' => $type['slug']],
                array_merge($type, ['is_active' => true]),
            );
        }
    }
}
