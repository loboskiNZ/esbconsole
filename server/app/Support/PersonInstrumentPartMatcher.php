<?php

namespace App\Support;

use App\Models\Library\InstrumentPart;
use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * First-pass conservative matcher between portal InstrumentReference names
 * and backend InstrumentPart catalog names. Formal catalog mapping may follow.
 */
class PersonInstrumentPartMatcher
{
    /** @var array<string, list<string>> */
    private const REFERENCE_TO_PART_NAMES = [
        'vocals' => ['Vocals', 'Singer', 'Voice1', 'Voice2', 'Voice3', 'Voice4', 'Voice5'],
        'electric guitar' => ['Guitar', 'Electric Guitar', 'Guitar 1', 'Guitar 2', 'AC gat'],
        'acoustic guitar' => ['Guitar', 'Acoustic Guitar', 'Guitar 1', 'Guitar 2'],
        'bass guitar' => ['Bass', 'Electric Bass', 'Bass Guitar'],
        'drums' => ['Drums', 'Drum Set', 'Bass Drum', 'Percussion', 'Percs'],
        'percussion' => ['Percussion', 'Percs', 'Drums'],
        'keys' => ['Keys', 'Keys1', 'Keyboard', 'Piano'],
        'accordion' => ['Accordion'],
        'machines' => ['Machines', 'Rhythm Section'],
        'trumpet' => ['Trumpet', 'Trumpet 1', 'Trumpet 2'],
        'trombone' => ['Trombone', 'Trombone 1', 'Trombone 2'],
        'clarinet' => ['Clarinet'],
        'sousaphone' => ['Sousaphone', 'Sous'],
        'cuatro' => ['Cuatro'],
    ];

    /** @var list<string> */
    private const ALTO_SAX_REFERENCE_KEYS = [
        'alto sax',
        'alto saxophone',
        'sax alto',
        'eb alto sax',
        'e-flat alto sax',
        'e flat alto sax',
        'alto sax in eb',
        'alto sax in e-flat',
        'alto sax in e flat',
        'alto_sax',
    ];

    /** @var list<string> */
    private const TENOR_SAX_REFERENCE_KEYS = [
        'tenor sax',
        'tenor saxophone',
        'sax tenor',
        'tenor sax in bb',
        'tenor sax in b-flat',
        'tenor_sax',
    ];

    /** @var list<string> */
    private const BARITONE_SAX_REFERENCE_KEYS = [
        'baritone sax',
        'baritone saxophone',
        'bari sax',
        'baritone_sax',
        'bari',
    ];

    /**
     * @return list<int>
     */
    public function matchingInstrumentPartIds(Person $person, int $bandId): array
    {
        $person->loadMissing('instruments');

        if ($person->instruments->isEmpty()) {
            return [];
        }

        $catalog = InstrumentPart::query()
            ->where('band_id', $bandId)
            ->where('active', true)
            ->get(['id', 'name']);

        if ($catalog->isEmpty()) {
            return [];
        }

        $matched = [];

        foreach ($person->instruments as $reference) {
            foreach ($catalog as $part) {
                if ($this->referenceMatchesPart($reference->name, $part->name)) {
                    $matched[] = $part->id;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    public function referenceMatchesPart(string $referenceName, string $partName): bool
    {
        $referenceKey = $this->normalizeName($referenceName);

        if ($this->isAltoSaxReference($referenceKey)) {
            return $this->partMatchesAltoSax($partName);
        }

        if ($this->isTenorSaxReference($referenceKey)) {
            return $this->partMatchesTenorSax($partName);
        }

        if ($this->isBaritoneSaxReference($referenceKey)) {
            return $this->partMatchesBaritoneSax($partName);
        }

        foreach ($this->candidatePartNames($referenceName) as $candidateName) {
            if ($this->namesEquivalent($candidateName, $partName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function candidatePartNames(string $referenceName): array
    {
        $normalizedKey = $this->normalizeName($referenceName);
        $candidates = [$referenceName];

        if (isset(self::REFERENCE_TO_PART_NAMES[$normalizedKey])) {
            $candidates = array_merge($candidates, self::REFERENCE_TO_PART_NAMES[$normalizedKey]);
        }

        foreach (self::REFERENCE_TO_PART_NAMES as $aliases) {
            if (in_array($referenceName, $aliases, true)) {
                $candidates = array_merge($candidates, $aliases);
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $candidates))));
    }

    private function isAltoSaxReference(string $referenceKey): bool
    {
        return in_array($referenceKey, self::ALTO_SAX_REFERENCE_KEYS, true)
            || $this->matchesNormalizedFamily($referenceKey, 'alto');
    }

    private function isTenorSaxReference(string $referenceKey): bool
    {
        return in_array($referenceKey, self::TENOR_SAX_REFERENCE_KEYS, true)
            || $this->matchesNormalizedFamily($referenceKey, 'tenor');
    }

    private function isBaritoneSaxReference(string $referenceKey): bool
    {
        return in_array($referenceKey, self::BARITONE_SAX_REFERENCE_KEYS, true)
            || $this->matchesNormalizedFamily($referenceKey, 'baritone')
            || $referenceKey === 'bari';
    }

    private function matchesNormalizedFamily(string $referenceKey, string $family): bool
    {
        if ($family === 'alto' && preg_match('/\b(?:tenor|baritone|bari)\b/', $referenceKey) === 1) {
            return false;
        }

        if ($family === 'tenor' && preg_match('/\b(?:alto|baritone|bari)\b/', $referenceKey) === 1) {
            return false;
        }

        if ($family === 'baritone' && preg_match('/\b(?:alto|tenor)\b/', $referenceKey) === 1) {
            return false;
        }

        return preg_match('/\b'.preg_quote($family, '/').'\b/', $referenceKey) === 1
            && preg_match('/\bsax\b/', $referenceKey) === 1;
    }

    private function partMatchesAltoSax(string $partName): bool
    {
        $normalized = $this->normalizeName($partName);

        if (in_array($normalized, ['alto', 'alto sax', 'sax alto', 'eb alto sax', 'e flat alto sax'], true)) {
            return true;
        }

        if ($this->containsOtherSaxFamily($normalized, 'alto')) {
            return false;
        }

        return preg_match('/\balto\b(?:\s+\S+)*\s+sax\b|\bsax\b(?:\s+\S+)*\s+alto\b/', $normalized) === 1;
    }

    private function partMatchesTenorSax(string $partName): bool
    {
        $normalized = $this->normalizeName($partName);

        if (in_array($normalized, ['tenor', 'tenor sax', 'sax tenor'], true)) {
            return true;
        }

        if ($this->containsOtherSaxFamily($normalized, 'tenor')) {
            return false;
        }

        return preg_match('/\btenor\b(?:\s+\S+)*\s+sax\b|\bsax\b(?:\s+\S+)*\s+tenor\b/', $normalized) === 1;
    }

    private function partMatchesBaritoneSax(string $partName): bool
    {
        $normalized = $this->normalizeName($partName);

        if (in_array($normalized, ['baritone sax', 'baritone', 'bari', 'bari sax', 'sax baritone'], true)) {
            return true;
        }

        if ($this->containsOtherSaxFamily($normalized, 'baritone')) {
            return false;
        }

        return preg_match('/\b(?:baritone|bari)\b(?:\s+\S+)*\s+sax\b|\bsax\b(?:\s+\S+)*\s+(?:baritone|bari)\b/', $normalized) === 1;
    }

    private function containsOtherSaxFamily(string $normalizedPartName, string $family): bool
    {
        $others = match ($family) {
            'alto' => ['tenor', 'baritone', 'bari'],
            'tenor' => ['alto', 'baritone', 'bari'],
            'baritone' => ['alto', 'tenor'],
            default => [],
        };

        foreach ($others as $other) {
            if (preg_match('/\b'.preg_quote($other, '/').'\b/', $normalizedPartName) === 1) {
                return true;
            }
        }

        return false;
    }

    private function namesEquivalent(string $left, string $right): bool
    {
        return $this->normalizeName($left) === $this->normalizeName($right);
    }

    private function normalizeName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/saxophone/', 'sax', $value) ?? $value;
        $value = preg_replace('/\s+in\s+(?:eb|e\s*flat|bb|b\s*flat)\s*$/', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? $value;

        return $value;
    }

    /**
     * @param  Collection<int, InstrumentPart>  $parts
     * @return list<int>
     */
    public function filterPartIds(Collection $parts, Person $person, int $bandId): array
    {
        $allowed = $this->matchingInstrumentPartIds($person, $bandId);

        return $parts
            ->pluck('id')
            ->filter(fn (int $id) => in_array($id, $allowed, true))
            ->values()
            ->all();
    }
}
