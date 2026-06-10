<?php

namespace Database\Factories;

use App\Models\InstrumentPart;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SongInstrumentPart>
 */
class SongInstrumentPartFactory extends Factory
{
    protected $model = SongInstrumentPart::class;

    public function definition(): array
    {
        return [
            'song_id' => Song::factory(),
            'instrument_part_id' => InstrumentPart::factory(),
            'notes' => null,
        ];
    }
}
