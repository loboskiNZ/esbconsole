<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait EnsuresPortalBand
{
    protected function ensurePortalBand(int $bandId = 1): void
    {
        if (! Schema::hasTable('bands')) {
            return;
        }

        if (DB::table('bands')->where('id', $bandId)->exists()) {
            return;
        }

        $now = now();

        DB::table('bands')->insert([
            'id' => $bandId,
            'public_id' => '00000000-0000-4000-8000-000000000001',
            'name' => 'Ed and the Shadow Boys',
            'primary_director_musician_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
