<?php

namespace Database\Seeders;

use App\Models\Band;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use Illuminate\Database\Seeder;

/**
 * Local-only demo playlist data for PH009 vertical slice verification.
 * Not production show data — run only in local/dev via DatabaseSeeder.
 */
class LocalDemoSeeder extends Seeder
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

        $songs = collect([
            'Local Demo Opener',
            'Local Demo Ballad',
            'Local Demo Closer',
        ])->map(fn (string $name) => Song::query()->firstOrCreate(
            ['band_id' => $band->id, 'name' => $name],
            ['lifecycle_state' => 'draft'],
        ));

        $showAlpha = Show::query()->firstOrCreate(
            ['band_id' => $band->id, 'name' => 'Local Demo Show A'],
            ['lifecycle_state' => 'draft'],
        );

        $showBeta = Show::query()->firstOrCreate(
            ['band_id' => $band->id, 'name' => 'Local Demo Show B'],
            ['lifecycle_state' => 'draft'],
        );

        $this->seedPlaylist($showAlpha, $songs->take(2)->values());
        $this->seedPlaylist($showBeta, $songs->values());
    }

    private function seedPlaylist(Show $show, $songs): void
    {
        if ($show->playlistItems()->exists()) {
            return;
        }

        foreach ($songs as $index => $song) {
            ShowPlaylistItem::create([
                'show_id' => $show->id,
                'song_id' => $song->id,
                'position' => $index + 1,
                'ableton_pgm' => $index + 1,
            ]);
        }
    }
}
