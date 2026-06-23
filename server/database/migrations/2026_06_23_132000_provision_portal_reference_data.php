<?php

use App\Support\InstrumentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('bands')->insertOrIgnore([
            'id' => 1,
            'public_id' => '00000000-0000-4000-8000-000000000001',
            'name' => 'Ed and the Shadow Boys',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (InstrumentCatalog::definitions() as $instrument) {
            DB::table('instrument_reference')->insertOrIgnore([
                'public_id' => (string) Str::uuid(),
                'slug' => $instrument['slug'],
                'name' => $instrument['name'],
                'family' => $instrument['family'] ?? null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('instrument_reference')->whereIn(
            'slug',
            array_column(InstrumentCatalog::definitions(), 'slug'),
        )->delete();

        DB::table('bands')->where('id', 1)->delete();
    }
};
