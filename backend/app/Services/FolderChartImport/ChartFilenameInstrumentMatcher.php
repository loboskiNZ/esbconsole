<?php

namespace App\Services\FolderChartImport;

final readonly class ChartFilenameMatch
{
    public function __construct(
        public bool $matched,
        public ?string $canonicalName,
        public ?string $slug,
        public string $source,
    ) {}
}

class ChartFilenameInstrumentMatcher
{
    /** @var array<string, string> */
    private const ALIASES = [
        'vocal' => 'Vocals',
        'vocals' => 'Vocals',
        'lead_vocal' => 'Lead Vocal',
        'lead vocal' => 'Lead Vocal',
        'bv' => 'Backing Vocals',
        'bvs' => 'Backing Vocals',
        'backing_vocals' => 'Backing Vocals',
        'backing vocals' => 'Backing Vocals',
        'guitar' => 'Guitar',
        'bass' => 'Bass',
        'drums' => 'Drums',
        'drummer' => 'Drums',
        'keys' => 'Keys',
        'keyboard' => 'Keys',
        'piano' => 'Piano',
        'sax' => 'Saxophone',
        'saxophone' => 'Saxophone',
        'trumpet' => 'Trumpet',
        'trombone' => 'Trombone',
        'horns' => 'Horns',
        'percussion' => 'Percussion',
        'machines' => 'Machines',
        'singer' => 'Singer',
    ];

    public function slug(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($value)) ?? '');
    }

    public function matchStem(string $filenameStem): ChartFilenameMatch
    {
        $trimmed = trim($filenameStem);

        if ($trimmed === '') {
            return new ChartFilenameMatch(false, null, null, 'empty_stem');
        }

        $slug = $this->slug($trimmed);

        if (isset(self::ALIASES[$slug])) {
            $name = self::ALIASES[$slug];

            return new ChartFilenameMatch(true, $name, $this->slug($name), 'alias');
        }

        $lower = strtolower($trimmed);

        if (isset(self::ALIASES[$lower])) {
            $name = self::ALIASES[$lower];

            return new ChartFilenameMatch(true, $name, $this->slug($name), 'alias');
        }

        return new ChartFilenameMatch(false, null, null, 'unmatched');
    }

    public function matchExistingCatalogName(string $catalogName): ChartFilenameMatch
    {
        $trimmed = trim($catalogName);

        if ($trimmed === '') {
            return new ChartFilenameMatch(false, null, null, 'empty_name');
        }

        return new ChartFilenameMatch(
            true,
            $trimmed,
            $this->slug($trimmed),
            'existing_catalog',
        );
    }
}
