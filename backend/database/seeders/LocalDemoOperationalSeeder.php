<?php

namespace Database\Seeders;

use App\Models\AbletonShowFile;
use App\Models\Band;
use App\Models\InstrumentPart;
use App\Models\Musician;
use App\Models\Performance;
use App\Models\PerformanceAssignment;
use App\Models\Readiness;
use App\Models\Show;
use App\Models\Soundcheck;
use Illuminate\Database\Seeder;

/**
 * Local Demo Data — PH011 Show/Performance operational domain.
 * Not production show data.
 */
class LocalDemoOperationalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $band = Band::query()->first();
        if (! $band) {
            return;
        }

        $show = Show::query()->where('band_id', $band->id)->where('name', 'Local Demo Show A')->first();
        if (! $show) {
            return;
        }

        if (! $show->ableton_show_file_id) {
            $file = AbletonShowFile::query()->create([
                'band_id' => $band->id,
                'name' => 'Local Demo Ableton File — Show A',
                'storage_reference' => 'local-demo/ableton/local-demo-show-a.als',
                'checksum' => 'demo-checksum-show-a',
                'notes' => 'Local Demo Data — metadata only',
            ]);
            $show->update(['ableton_show_file_id' => $file->id]);
        }

        $performance = Performance::query()->firstOrCreate(
            [
                'show_id' => $show->id,
                'venue' => 'Local Demo Venue',
                'performance_date' => now()->addMonth()->toDateString(),
            ],
            [
                'band_id' => $band->id,
                'status' => Performance::STATUS_PLANNED,
                'notes' => 'Local Demo Data — not a real performance',
            ],
        );

        $musician = Musician::query()->where('band_id', $band->id)->first();
        $guitar = InstrumentPart::query()->where('band_id', $band->id)->where('name', 'Guitar')->first();

        if ($musician && $guitar) {
            PerformanceAssignment::query()->firstOrCreate(
                [
                    'performance_id' => $performance->id,
                    'musician_id' => $musician->id,
                    'instrument_part_id' => $guitar->id,
                ],
                ['active' => true],
            );
        }

        Soundcheck::query()->firstOrCreate(
            ['performance_id' => $performance->id],
            [
                'status' => Soundcheck::STATUS_NOT_STARTED,
                'notes' => 'Local Demo Data — soundcheck foundation',
            ],
        );

        Readiness::query()->firstOrCreate(
            ['performance_id' => $performance->id],
            [
                'status' => Readiness::STATUS_NOT_READY,
                'notes' => 'Local Demo Data — readiness foundation',
            ],
        );
    }
}
