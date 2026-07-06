<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

class SongLyricsDocxRenderer
{
    /**
     * @param  list<array{tag: ?string, heading: ?string, lines: list<string>}>  $sections
     */
    public function render(
        string $outputDocxPath,
        string $songTitle,
        ?string $metadata,
        array $sections,
    ): void {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Georgia');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop' => 1134,
            'marginBottom' => 1134,
            'marginLeft' => 1134,
            'marginRight' => 1134,
        ]);

        $section->addText($songTitle, ['bold' => true, 'size' => 20, 'name' => 'Georgia']);

        if ($metadata !== null && $metadata !== '') {
            $section->addTextBreak();
            $section->addText($metadata, ['size' => 10, 'color' => '333333', 'name' => 'Arial']);
        }

        foreach ($sections as $lyricsSection) {
            if (! empty($lyricsSection['heading'])) {
                $section->addTextBreak();
                $section->addText(
                    strtoupper($lyricsSection['heading']),
                    ['bold' => true, 'size' => 11, 'name' => 'Arial'],
                );
            }

            foreach ($lyricsSection['lines'] as $line) {
                if ($line === '') {
                    $section->addTextBreak();
                } else {
                    $section->addText($line, ['size' => 12, 'name' => 'Georgia'], ['spaceAfter' => 60]);
                }
            }
        }

        $directory = dirname($outputDocxPath);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create temporary directory at {$directory}.");
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputDocxPath);
    }
}
