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
        'electric_guitar' => 'Guitar',
        'gat' => 'Guitar',
        'gat1' => 'Guitar 1',
        'gat2' => 'Guitar 2',
        'bass' => 'Bass',
        'electric_bass' => 'Bass',
        'bass_guitar' => 'Bass',
        'drums' => 'Drums',
        'drummer' => 'Drums',
        'drum_set' => 'Drums',
        'bass_drum' => 'Drums',
        'percussion' => 'Percussion',
        'keys' => 'Keys',
        'keyboard' => 'Keys',
        'keyboards' => 'Keys',
        'piano' => 'Piano',
        'alto' => 'Alto Sax',
        'alto_sax' => 'Alto Sax',
        'alto saxophone' => 'Alto Sax',
        'tenor' => 'Tenor Sax',
        'tenor_sax' => 'Tenor Sax',
        'tenor saxophone' => 'Tenor Sax',
        'baritone' => 'Baritone Sax',
        'baritone_sax' => 'Baritone Sax',
        'baritone saxophone' => 'Baritone Sax',
        'bari' => 'Baritone Sax',
        'sax' => 'Saxophone',
        'saxophone' => 'Saxophone',
        'trumpet' => 'Trumpet',
        'trump' => 'Trumpet',
        'trumpet_in_bb' => 'Trumpet',
        'trumpet_in_b_b' => 'Trumpet',
        'trombone' => 'Trombone',
        'tromb' => 'Trombone',
        'tenor_trombone' => 'Trombone',
        'bone1' => 'Trombone 1',
        'bone_1' => 'Trombone 1',
        'bone2' => 'Trombone 2',
        'bone_2' => 'Trombone 2',
        'horns' => 'Horns',
        'machines' => 'Machines',
        'singer' => 'Singer',
        'sous' => 'Sousaphone',
        'sousaphone' => 'Sousaphone',
        'rhythm_section' => 'Rhythm Section',
    ];

    /** @var list<string> */
    private const SKIP_TOKENS = [
        'full score',
        'full_score',
        'lyrics',
        'score',
        '',
    ];

    public function slug(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($value)) ?? '');
    }

    public function matchStem(string $filenameStem): ChartFilenameMatch
    {
        foreach ($this->candidateTokens($filenameStem) as $token) {
            if ($this->shouldSkipToken($token)) {
                continue;
            }

            $match = $this->matchToken($token);

            if ($match->matched) {
                return $match;
            }
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

    /**
     * @return list<string>
     */
    private function candidateTokens(string $filenameStem): array
    {
        $tokens = [];
        $stem = trim($filenameStem);

        if (preg_match('/\(([^()]+)\)\s*$/', $stem, $matches) === 1) {
            $tokens[] = trim($matches[1]);
        }

        if (str_contains($stem, ' - ')) {
            $tokens[] = trim((string) preg_replace('/.* - /', '', $stem));
        }

        if (preg_match('/\(([^()]+)\)/', $stem, $matches) === 1) {
            $tokens[] = trim($matches[1]);
        }

        $tokens[] = $stem;

        $unique = [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            $unique[$this->slug($token)] = $token;
        }

        return array_values($unique);
    }

    private function shouldSkipToken(string $token): bool
    {
        $normalized = strtolower(trim($token));

        return in_array($normalized, self::SKIP_TOKENS, true)
            || str_contains($normalized, 'lyrics')
            || str_contains($normalized, 'full score');
    }

    private function matchToken(string $token): ChartFilenameMatch
    {
        $trimmed = trim($token);

        if ($trimmed === '') {
            return new ChartFilenameMatch(false, null, null, 'empty_stem');
        }

        $slug = $this->slug($trimmed);
        $lower = strtolower($trimmed);

        if (isset(self::ALIASES[$slug])) {
            $name = self::ALIASES[$slug];

            return new ChartFilenameMatch(true, $name, $this->slug($name), 'alias');
        }

        if (isset(self::ALIASES[$lower])) {
            $name = self::ALIASES[$lower];

            return new ChartFilenameMatch(true, $name, $this->slug($name), 'alias');
        }

        $normalized = preg_replace('/\s+in\s+bb\s*$/i', '', $trimmed) ?? $trimmed;
        $normalizedSlug = $this->slug($normalized);

        if ($normalized !== $trimmed && isset(self::ALIASES[$normalizedSlug])) {
            $name = self::ALIASES[$normalizedSlug];

            return new ChartFilenameMatch(true, $name, $this->slug($name), 'alias');
        }

        if (preg_match('/^(alto|tenor|baritone)\s+sax(?:ophone)?$/i', $normalized, $matches) === 1) {
            $name = ucfirst(strtolower($matches[1])).' Sax';

            return new ChartFilenameMatch(true, $name, $this->slug($name), 'alias');
        }

        if (preg_match('/^trombone\s+(\d+)$/i', $normalized, $matches) === 1) {
            $name = 'Trombone '.$matches[1];

            return new ChartFilenameMatch(true, $name, $this->slug($name), 'alias');
        }

        if (preg_match('/^trumpet\s+in\s+bb\s+(\d+)?$/i', $normalized, $matches) === 1) {
            $name = isset($matches[1]) && $matches[1] !== ''
                ? 'Trumpet '.$matches[1]
                : 'Trumpet';

            return new ChartFilenameMatch(true, $name, $this->slug($name), 'alias');
        }

        return new ChartFilenameMatch(false, null, null, 'unmatched');
    }
}
