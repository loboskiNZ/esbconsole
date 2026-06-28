<?php

namespace App\Services;

use App\Models\Library\InstrumentPart;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StudioSongInstrumentPartService
{
    /**
     * @return list<array{
     *     song_instrument_part_id: int,
     *     chart_id: int|null,
     *     name: string,
     *     has_chart: bool,
     *     chart_status_label: string,
     * }>
     */
    public function partsForSongEdit(Song $song): array
    {
        $song->loadMissing(['songInstrumentParts.instrumentPart', 'songInstrumentParts.chart']);

        return $song->songInstrumentParts
            ->sortBy(fn (SongInstrumentPart $row) => $row->instrumentPart?->name ?? '')
            ->map(function (SongInstrumentPart $row): array {
                $name = $row->instrumentPart?->name ?? 'Instrument part';
                $hasChart = $row->chart_id !== null;

                return [
                    'song_instrument_part_id' => $row->id,
                    'chart_id' => $row->chart_id,
                    'name' => $name,
                    'has_chart' => $hasChart,
                    'chart_status_label' => $hasChart ? 'chart available' : 'chart missing',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, InstrumentPart>
     */
    public function attachablePartsForSong(Song $song, ?int $bandId = null): Collection
    {
        $bandId ??= (int) config('portal.band_id', 1);

        abort_unless($song->band_id === $bandId, 404);

        $assignedIds = $song->songInstrumentParts()->pluck('instrument_part_id');

        return InstrumentPart::query()
            ->where('band_id', $bandId)
            ->where('active', true)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();
    }

    public function attachExistingPart(Song $song, int $instrumentPartId, ?int $bandId = null): SongInstrumentPart
    {
        $bandId ??= (int) config('portal.band_id', 1);

        abort_unless($song->band_id === $bandId, 404);

        $instrumentPart = InstrumentPart::query()
            ->where('band_id', $bandId)
            ->where('active', true)
            ->findOrFail($instrumentPartId);

        if ($song->songInstrumentParts()->where('instrument_part_id', $instrumentPart->id)->exists()) {
            throw new InvalidArgumentException('Instrument part is already assigned to this song.');
        }

        return SongInstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'instrument_part_id' => $instrumentPart->id,
        ]);
    }

    public function createAndAttachPart(Song $song, string $name, ?int $bandId = null): SongInstrumentPart
    {
        $bandId ??= (int) config('portal.band_id', 1);
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            throw new InvalidArgumentException('Instrument part name is required.');
        }

        abort_unless($song->band_id === $bandId, 404);

        $instrumentPart = InstrumentPart::query()
            ->where('band_id', $bandId)
            ->where('active', true)
            ->whereRaw('lower(name) = ?', [mb_strtolower($normalizedName)])
            ->first();

        if ($instrumentPart === null) {
            $instrumentPart = InstrumentPart::query()->create([
                'public_id' => (string) Str::uuid(),
                'band_id' => $bandId,
                'name' => $normalizedName,
                'active' => true,
            ]);
        }

        if ($song->songInstrumentParts()->where('instrument_part_id', $instrumentPart->id)->exists()) {
            throw new InvalidArgumentException('Instrument part is already assigned to this song.');
        }

        return SongInstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'instrument_part_id' => $instrumentPart->id,
        ]);
    }

    /**
     * Detach an instrument part from this song only. Preserves the global instrument
     * part definition and any linked chart record or file.
     */
    public function detachFromSong(
        Song $song,
        SongInstrumentPart $songInstrumentPart,
        ?int $bandId = null,
    ): bool {
        $bandId ??= (int) config('portal.band_id', 1);

        abort_unless($song->band_id === $bandId, 404);
        abort_unless((int) $songInstrumentPart->song_id === (int) $song->id, 404);

        $hadChart = $songInstrumentPart->chart_id !== null;

        $songInstrumentPart->delete();

        return $hadChart;
    }
}
