<?php

namespace App\Data;

readonly class StudioChartSearchResult
{
    /**
     * @param  list<string>  $parts
     */
    public function __construct(
        public int $songId,
        public string $name,
        public string $url,
        public array $parts,
    ) {}

    /**
     * @return array{song_id: int, name: string, url: string, parts: list<string>}
     */
    public function toArray(): array
    {
        return [
            'song_id' => $this->songId,
            'name' => $this->name,
            'url' => $this->url,
            'parts' => $this->parts,
        ];
    }
}
