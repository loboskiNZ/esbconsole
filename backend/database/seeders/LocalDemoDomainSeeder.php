<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Band;
use App\Models\Capability;
use App\Models\Chart;
use App\Models\Cue;
use App\Models\Device;
use App\Models\InstrumentPart;
use App\Models\Musician;
use App\Models\Snippet;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use Illuminate\Database\Seeder;

/**
 * Local Demo Data — PH010 core domain entities for local/dev verification only.
 * Not production show data.
 */
class LocalDemoDomainSeeder extends Seeder
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

        $instrumentParts = collect([
            'Lead Vocal',
            'Guitar',
            'Bass',
            'Drums',
            'Trumpet',
        ])->map(fn (string $name) => InstrumentPart::query()->firstOrCreate(
            ['band_id' => $band->id, 'name' => $name],
            ['description' => "Local Demo Data — {$name}", 'active' => true],
        ));

        $musician = Musician::query()->firstOrCreate(
            ['band_id' => $band->id, 'display_name' => 'Local Demo Musician'],
            [
                'first_name' => 'Local',
                'last_name' => 'Demo',
                'email' => null,
                'notes' => 'Local Demo Data — not a real musician',
                'active' => true,
            ],
        );

        Device::query()->firstOrCreate(
            ['musician_id' => $musician->id, 'device_name' => 'Local Demo Tablet'],
            ['device_type' => 'tablet', 'active' => true],
        );

        $leadVocal = $instrumentParts->firstWhere('name', 'Lead Vocal');
        $guitar = $instrumentParts->firstWhere('name', 'Guitar');

        Capability::query()->firstOrCreate([
            'musician_id' => $musician->id,
            'instrument_part_id' => $leadVocal->id,
        ]);

        Assignment::query()->firstOrCreate(
            ['musician_id' => $musician->id, 'instrument_part_id' => $guitar->id],
            ['active' => true],
        );

        $song = Song::query()->updateOrCreate(
            ['band_id' => $band->id, 'song_code' => '001'],
            [
                'name' => 'Local Demo Song Alpha',
                'bpm' => 120,
                'description' => 'Local Demo Data',
                'status' => Song::STATUS_DRAFT,
            ],
        );

        $cues = collect([
            ['000', 'Preparation'],
            ['001', 'Intro'],
            ['002', 'Verse 1'],
            ['003', 'Chorus'],
        ])->map(fn (array $cue) => Cue::query()->firstOrCreate(
            ['song_id' => $song->id, 'cue_number' => $cue[0]],
            ['name' => $cue[1], 'description' => 'Local Demo Data'],
        ));

        $songLeadVocal = SongInstrumentPart::query()->firstOrCreate(
            ['song_id' => $song->id, 'instrument_part_id' => $leadVocal->id],
            ['notes' => 'Local Demo Data — vocal chart lane'],
        );

        $songGuitar = SongInstrumentPart::query()->firstOrCreate(
            ['song_id' => $song->id, 'instrument_part_id' => $guitar->id],
            ['notes' => 'Local Demo Data — guitar chart lane'],
        );

        Chart::query()->firstOrCreate(
            ['song_id' => $song->id, 'title' => 'Local Demo Vocal Chart'],
            [
                'storage_reference' => 'local-demo/charts/demo-vocal.pdf',
                'checksum' => 'demo-checksum-vocal',
                'notes' => 'Local Demo Data — metadata only',
            ],
        );

        $vocalChart = Chart::query()
            ->where('song_id', $song->id)
            ->where('title', 'Local Demo Vocal Chart')
            ->first();

        $songLeadVocal->update(['chart_id' => $vocalChart->id]);

        $chorusCue = $cues->firstWhere('cue_number', '003');

        Snippet::query()->firstOrCreate(
            [
                'song_instrument_part_id' => $songGuitar->id,
                'cue_id' => $chorusCue->id,
                'is_active' => true,
            ],
            [
                'source_type' => Snippet::SOURCE_CHART_CROP,
                'freshness_state' => Snippet::FRESHNESS_CURRENT,
                'title' => 'Local Demo Guitar Snippet — Chorus',
                'storage_reference' => 'local-demo/snippets/demo-guitar-chorus.png',
                'checksum' => 'demo-checksum-snippet',
                'notes' => 'Local Demo Data — metadata only',
            ],
        );
    }
}
