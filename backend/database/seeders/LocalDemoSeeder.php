<?php

namespace Database\Seeders;

use App\Models\AbletonShowFile;
use App\Models\Band;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use Illuminate\Database\Seeder;

/**
 * Local Demo Data — PH009 playlist slice + PH010 song codes + PH011 show files.
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
            ['002', 'Local Demo Opener'],
            ['003', 'Local Demo Ballad'],
            ['004', 'Local Demo Closer'],
        ])->map(fn (array $row) => Song::query()->firstOrCreate(
            ['band_id' => $band->id, 'song_code' => $row[0]],
            ['name' => $row[1], 'status' => Song::STATUS_DRAFT],
        ));

        $showAlpha = $this->ensureShow($band, 'Local Demo Show A', 'local-demo-show-a');
        $showBeta = $this->ensureShow($band, 'Local Demo Show B', 'local-demo-show-b');

        $this->seedPlaylist($showAlpha, $songs->take(2)->values());
        $this->seedPlaylist($showBeta, $songs->values());
    }

    private function ensureShow(Band $band, string $name, string $slug): Show
    {
        $show = Show::query()->firstOrCreate(
            ['band_id' => $band->id, 'name' => $name],
            ['lifecycle_state' => 'draft'],
        );

        if (! $show->ableton_show_file_id) {
            $file = AbletonShowFile::query()->create([
                'band_id' => $band->id,
                'name' => "Local Demo Ableton File — {$name}",
                'storage_reference' => "local-demo/ableton/{$slug}.als",
                'checksum' => 'demo-checksum-'.$slug,
                'notes' => 'Local Demo Data — metadata only',
            ]);
            $show->update(['ableton_show_file_id' => $file->id]);
        }

        return $show->fresh();
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
