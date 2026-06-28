<?php

namespace App\Services;

use App\Contracts\DocxToPdfConverterInterface;
use App\Models\Library\Song;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\ShowSetlistGeneration;
use App\Models\User;
use App\Support\CloudStudioMediaStorage;
use App\Support\StudioLibraryAvailability;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class StudioShowSetlistPdfService
{
    public function __construct(
        private readonly StudioShowService $shows,
        private readonly StudioShowPlaylistService $playlist,
        private readonly StudioLibraryAvailability $library,
        private readonly ShowSetlistTemplateRenderer $templateRenderer,
        private readonly DocxToPdfConverterInterface $pdfConverter,
        private readonly CloudStudioMediaStorage $mediaStorage,
    ) {}

    public function latestForShow(int $showId, ?int $bandId = null): ?ShowSetlistGeneration
    {
        $show = $this->shows->showForPortal($showId, $bandId);

        return ShowSetlistGeneration::query()
            ->where('show_id', $show->id)
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function generate(Show $show, User $director, ?int $bandId = null): ShowSetlistGeneration
    {
        abort_unless($director->isDirector(), 403);

        $bandId ??= (int) config('portal.band_id', 1);
        $portalShow = $this->shows->showForPortal($show->id, $bandId);

        if (! $this->library->isAvailable()) {
            throw new RuntimeException('Music library is not available.');
        }

        $entries = $this->playlist->playlistEntriesForShow(
            $portalShow->id,
            $bandId,
            viewer: $director,
            isDirector: true,
        );

        if ($entries->isEmpty()) {
            throw new InvalidArgumentException('Add songs to the playlist before generating a setlist PDF.');
        }

        $templatePath = $this->templatePath();
        $templateReference = $this->relativeTemplateReference($templatePath);
        $playlistHash = $this->playlistHash($portalShow->id);
        $playlistView = $this->playlist->playlistViewForShow($portalShow->id, $bandId, viewer: $director);
        $summary = $playlistView['summary'];
        $songs = $this->songRowsForTemplate($entries);
        $directorName = $director->person?->artistic_name
            ?: $director->name
            ?: 'Director';
        $timestamp = now()->format('YmdHis');
        $storageReference = $this->mediaStorage->setlistReference($portalShow->id, $timestamp);
        $tempDirectory = $this->tempDirectoryForShow($portalShow->id);
        $tempDocx = $tempDirectory.'/setlist.docx';
        $tempPdf = $tempDirectory.'/setlist.pdf';

        try {
            $this->templateRenderer->render(
                templatePath: $templatePath,
                outputDocxPath: $tempDocx,
                setlistName: (string) $portalShow->name,
                songs: $songs,
                headerValues: [
                    'show_name' => (string) $portalShow->name,
                    'generated_at' => now()->timezone(config('app.timezone'))->format('j M Y, g:i A'),
                    'generated_by' => (string) $directorName,
                    'song_count' => (string) $summary['song_count'],
                    'chart_count' => (string) $summary['charts_count'],
                    'instrument_part_count' => (string) $summary['instrument_part_count'],
                    'estimated_duration' => (string) $summary['estimated_duration_label'],
                ],
            );

            $this->assertRenderedDocxIsValid($tempDocx);

            $this->pdfConverter->convert($tempDocx, $tempPdf);

            $pdfContents = file_get_contents($tempPdf);

            if ($pdfContents === false) {
                throw new RuntimeException('Unable to read generated setlist PDF.');
            }

            $this->mediaStorage->putMediaObject($storageReference, $pdfContents);

            return DB::transaction(function () use (
                $portalShow,
                $director,
                $storageReference,
                $playlistHash,
                $templateReference,
            ): ShowSetlistGeneration {
                return ShowSetlistGeneration::query()->create([
                    'show_id' => $portalShow->id,
                    'storage_disk' => $this->mediaStorage->mediaDisk(),
                    'storage_reference' => $storageReference,
                    'generated_by' => $director->id,
                    'generated_at' => now(),
                    'playlist_hash' => $playlistHash,
                    'template_reference' => $templateReference,
                ]);
            });
        } finally {
            $this->cleanupTempDirectory($tempDirectory);
        }
    }

    public function playlistHash(int $showId): string
    {
        $items = ShowPlaylistItem::query()
            ->where('show_id', $showId)
            ->active()
            ->orderBy('position')
            ->get(['id', 'position', 'song_id', 'notes']);

        $payload = $items->map(static fn (ShowPlaylistItem $item): array => [
            'id' => (int) $item->id,
            'position' => (int) $item->position,
            'song_id' => (int) $item->song_id,
            'notes' => $item->notes,
        ])->values()->all();

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  Collection<int, array{
     *     item: ShowPlaylistItem,
     *     metadata: array<string, mixed>,
     *     instrument_parts: list<array<string, mixed>>,
     *     required_part_count: int,
     * }>  $entries
     * @return list<array{
     *     idx: int,
     *     title: string,
     *     song_code: string,
     *     key: string,
     *     bpm: string,
     *     duration: string,
     *     instrument_parts: string,
     *     notes: string,
     * }>
     */
    private function songRowsForTemplate(Collection $entries): array
    {
        $rows = [];

        foreach ($entries->values() as $index => $entry) {
            /** @var ShowPlaylistItem $item */
            $item = $entry['item'];
            /** @var Song|null $song */
            $song = $item->song;
            $metadata = $entry['metadata'];
            $partNames = collect($entry['instrument_parts'])
                ->pluck('name')
                ->filter()
                ->values()
                ->all();

            $rows[] = [
                'idx' => $index + 1,
                'title' => $song !== null ? (string) $song->name : 'Unknown song',
                'song_code' => $song !== null ? (string) $song->song_code : '',
                'key' => filled($metadata['musical_key'] ?? null) ? (string) $metadata['musical_key'] : '—',
                'bpm' => filled($metadata['bpm'] ?? null) ? (string) $metadata['bpm'] : '—',
                'duration' => $this->durationLabelForSong($song),
                'instrument_parts' => $partNames !== [] ? implode(', ', $partNames) : '—',
                'notes' => $this->notesForSetlistRow($item, $song),
            ];
        }

        return $rows;
    }

    private function notesForSetlistRow(ShowPlaylistItem $item, ?Song $song): string
    {
        if (filled($item->notes)) {
            return trim((string) $item->notes);
        }

        if ($song !== null && filled($song->notes)) {
            return trim((string) $song->notes);
        }

        return '';
    }

    private function durationLabelForSong(?Song $song): string
    {
        if ($song === null || $song->duration_seconds === null) {
            return '—';
        }

        return $this->playlist->formatEstimatedDurationLabel((int) $song->duration_seconds);
    }

    private function templatePath(): string
    {
        $configured = config('portal.setlist_template_path');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $default = dirname(base_path()).'/templates/esb_setlist_template_tagged.docx';

        if (! is_file($default)) {
            throw new RuntimeException('Setlist DOCX template is not configured or missing.');
        }

        return $default;
    }

    private function relativeTemplateReference(string $templatePath): string
    {
        $repoRoot = dirname(base_path());

        if (str_starts_with($templatePath, $repoRoot)) {
            return ltrim(substr($templatePath, strlen($repoRoot)), '/');
        }

        return basename($templatePath);
    }

    private function tempDirectoryForShow(int $showId): string
    {
        $directory = rtrim(sys_get_temp_dir(), '/').'/esb-setlists/'.$showId.'/'.Str::uuid();

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create temporary directory at {$directory}.");
        }

        return $directory;
    }

    private function assertRenderedDocxIsValid(string $docxPath): void
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException('Generated setlist DOCX is missing.');
        }

        $size = filesize($docxPath);

        if ($size === false || $size < 1024) {
            throw new RuntimeException('Generated setlist DOCX is empty or invalid.');
        }

        $zip = new \ZipArchive;

        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException('Generated setlist DOCX is not a valid archive.');
        }

        $zip->close();
    }

    private function cleanupTempDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($directory);

        $parent = dirname($directory);
        if (is_dir($parent) && (glob($parent.'/*') ?: []) === []) {
            @rmdir($parent);
        }
    }
}
