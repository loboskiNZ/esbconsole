<?php

namespace Database\Factories;

use App\Models\Cue;
use App\Models\Snippet;
use App\Models\SongInstrumentPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Snippet>
 */
class SnippetFactory extends Factory
{
    protected $model = Snippet::class;

    public function definition(): array
    {
        return [
            'song_instrument_part_id' => SongInstrumentPart::factory(),
            'cue_id' => Cue::factory(),
            'title' => 'Test Snippet '.fake()->word(),
            'storage_reference' => 'local-demo/snippets/'.fake()->uuid().'.png',
            'checksum' => fake()->sha256(),
            'notes' => null,
        ];
    }
}
