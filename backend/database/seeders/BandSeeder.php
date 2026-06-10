<?php

namespace Database\Seeders;

use App\Models\Band;
use Illuminate\Database\Seeder;

class BandSeeder extends Seeder
{
    public function run(): void
    {
        Band::query()->firstOrCreate(
            ['name' => 'Ed and the Shadow Boys'],
            [],
        );
    }
}
