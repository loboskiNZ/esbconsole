<?php

namespace Database\Factories;

use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowPlaylistItem>
 */
class ShowPlaylistItemFactory extends Factory
{
    protected $model = ShowPlaylistItem::class;

    public function definition(): array
    {
        return [
            'show_id' => Show::factory(),
            'song_id' => Song::factory(),
            'position' => 1,
            'ableton_pgm' => 1,
        ];
    }
}
