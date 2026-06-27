<?php

namespace App\Services;

use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Support\StudioLibraryChartStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StudioShowPlaylistChartService
{
    public function __construct(
        private readonly StudioLibraryChartStorage $chartStorage,
    ) {}

    public function songInstrumentPartForShowPlaylist(
        Show $show,
        Song $song,
        SongInstrumentPart $songInstrumentPart,
        ?int $bandId = null,
    ): SongInstrumentPart {
        $bandId ??= (int) config('portal.band_id', 1);

        abort_unless((int) $song->band_id === $bandId, 404);
        abort_unless((int) $songInstrumentPart->song_id === (int) $song->id, 404);

        $onActivePlaylist = ShowPlaylistItem::query()
            ->where('show_id', $show->id)
            ->where('song_id', $song->id)
            ->active()
            ->exists();

        abort_unless($onActivePlaylist, 404);

        return $songInstrumentPart->loadMissing(['instrumentPart', 'chart', 'song']);
    }

    public function uploadChartForSongInstrumentPart(
        SongInstrumentPart $songInstrumentPart,
        UploadedFile $file,
    ): Chart {
        if ($songInstrumentPart->chart_id !== null) {
            throw new InvalidArgumentException('This instrument part already has a chart.');
        }

        $songInstrumentPart->loadMissing(['instrumentPart', 'song']);
        $song = $songInstrumentPart->song;

        abort_unless($song !== null, 404);

        $partName = $songInstrumentPart->instrumentPart?->name ?? 'Chart';
        $bandId = (int) $song->band_id;
        $filename = Str::slug($partName).'-'.Str::lower(Str::random(8)).'.pdf';
        $storageReference = "charts/{$bandId}/{$song->song_code}/{$filename}";
        $diskPath = $this->chartStorage->diskRelativePath($storageReference);
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read uploaded chart file.');
        }

        $checksum = hash('sha256', $contents);

        return DB::connection($songInstrumentPart->getConnectionName())->transaction(function () use (
            $songInstrumentPart,
            $song,
            $partName,
            $file,
            $storageReference,
            $diskPath,
            $contents,
            $checksum,
        ): Chart {
            $diskName = (string) config('portal.library_chart_disk', 'library');
            $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
            $directory = trim(str_replace('\\', '/', dirname($diskPath)), '/');

            if ($directory !== '' && $directory !== '.' && ! $disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }

            $disk->put($diskPath, $contents);

            $chart = Chart::query()->create([
                'public_id' => (string) Str::uuid(),
                'song_id' => $song->id,
                'title' => $partName.' Chart',
                'original_filename' => $file->getClientOriginalName(),
                'storage_reference' => $storageReference,
                'checksum' => $checksum,
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'file_size' => $file->getSize(),
            ]);

            $songInstrumentPart->update(['chart_id' => $chart->id]);

            return $chart;
        });
    }
}
