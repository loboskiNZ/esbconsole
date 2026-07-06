<?php

namespace App\Services;

use Illuminate\Support\Str;

class TaggedLyricsParser
{
    /**
     * @return list<array{tag: ?string, heading: ?string, lines: list<string>}>
     */
    public function parse(string $text): array
    {
        $sections = [];
        $current = null;

        foreach ($this->splitLines($text) as $line) {
            if ($this->isTagLine($line)) {
                if ($current !== null) {
                    $sections[] = $current;
                }

                $tag = trim($line);

                $current = [
                    'tag' => $tag,
                    'heading' => $this->humanizeTag($tag),
                    'lines' => [],
                ];

                continue;
            }

            if ($current === null) {
                $current = [
                    'tag' => null,
                    'heading' => null,
                    'lines' => [],
                ];
            }

            $current['lines'][] = $line;
        }

        if ($current !== null) {
            $sections[] = $current;
        }

        return $sections;
    }

    public function isTagLine(string $line): bool
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return false;
        }

        return preg_match('/^\{[^}]+\}$/', $trimmed) === 1;
    }

    public function humanizeTag(string $tag): string
    {
        $inner = trim($tag, '{}');
        $inner = str_replace(['_', '-'], ' ', $inner);
        $inner = preg_replace('/(\D)(\d+)/', '$1 $2', $inner) ?? $inner;
        $inner = preg_replace('/\s+/', ' ', $inner) ?? $inner;

        return Str::title(trim($inner));
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return preg_split("/\r\n|\r|\n/", $text) ?: [];
    }
}
