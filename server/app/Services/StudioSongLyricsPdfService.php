<?php

namespace App\Services;

use App\Contracts\DocxToPdfConverterInterface;
use App\Models\Library\Song;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class StudioSongLyricsPdfService
{
    public function __construct(
        private readonly TaggedLyricsParser $parser,
        private readonly SongLyricsDocxRenderer $docxRenderer,
        private readonly DocxToPdfConverterInterface $pdfConverter,
        private readonly SongAssetStorageService $assetStorage,
    ) {}

    public function filenameFor(Song $song): string
    {
        $slug = str($song->name)->slug('_')->limit(60, '');

        return ($slug !== '' ? $slug : 'song').'-lyrics.pdf';
    }

    /**
     * @return array{contents: string, filename: string}
     */
    public function generateFromSavedLyrics(Song $song, User $director): array
    {
        $song->loadMissing(['musicalKey', 'timeSignature']);

        $lyrics = trim((string) $song->lyrics);

        if ($lyrics === '') {
            throw new InvalidArgumentException('Save lyrics for this song before generating a PDF.');
        }

        $sections = $this->parser->parse($lyrics);
        $metadata = $this->metadataLine($song);

        $tempDirectory = $this->createTempDirectory();
        $docxPath = $tempDirectory.'/lyrics.docx';
        $pdfPath = $tempDirectory.'/lyrics.pdf';

        try {
            $this->docxRenderer->render($docxPath, $song->name, $metadata, $sections);

            $this->pdfConverter->convert($docxPath, $pdfPath);

            $contents = file_get_contents($pdfPath);

            if ($contents === false || $contents === '') {
                throw new RuntimeException('Lyrics PDF conversion produced an empty file.');
            }

            $this->assetStorage->storeGeneratedLyricsPdf($song, $contents, $director);

            return [
                'contents' => $contents,
                'filename' => $this->filenameFor($song),
            ];
        } finally {
            $this->cleanupTempDirectory($tempDirectory);
        }
    }

    private function metadataLine(Song $song): ?string
    {
        $parts = [];

        if ($song->musicalKey?->label) {
            $parts[] = 'Key: '.$song->musicalKey->label;
        }

        if ($song->bpm) {
            $parts[] = 'BPM: '.$song->bpm;
        }

        if (filled($song->tempo_feel)) {
            $parts[] = 'Tempo: '.$song->tempo_feel;
        }

        if ($song->timeSignature?->label) {
            $parts[] = 'Time: '.$song->timeSignature->label;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function createTempDirectory(): string
    {
        $directory = sys_get_temp_dir().'/song-lyrics-'.Str::lower(Str::random(12));

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create temporary directory at {$directory}.");
        }

        return $directory;
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
    }
}
