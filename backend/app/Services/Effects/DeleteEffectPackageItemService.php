<?php

namespace App\Services\Effects;

use App\Models\EffectPackageItem;

class DeleteEffectPackageItemService
{
    public function delete(EffectPackageItem $item): int
    {
        $packageId = $item->effect_package_id;
        $item->delete();

        return $packageId;
    }
}
