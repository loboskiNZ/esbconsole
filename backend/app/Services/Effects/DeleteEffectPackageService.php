<?php

namespace App\Services\Effects;

use App\Models\EffectPackage;
use Illuminate\Support\Facades\DB;

class DeleteEffectPackageService
{
    public function delete(EffectPackage $package): void
    {
        DB::transaction(function () use ($package): void {
            $package->songEffectAssignments()->delete();
            $package->delete();
        });
    }
}
