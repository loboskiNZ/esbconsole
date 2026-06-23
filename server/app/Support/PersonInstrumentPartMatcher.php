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
        'alto sax' => ['Alto Sax', 'Alto'],
        'tenor sax' => ['Tenor Sax', 'Tenor'],
        'baritone sax' => ['Baritone Sax', 'Bari', 'Baritone'],
        'sousaphone' => ['Sousaphone', 'Sous'],
        'cuatro' => ['Cuatro'],
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
            foreach ($this->candidatePartNames($reference->name) as $candidateName) {
                foreach ($catalog as $part) {
                    if ($this->namesEquivalent($candidateName, $part->name)) {
                        $matched[] = $part->id;
                    }
                }
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * @return list<string>
     */
    private function candidatePartNames(string $referenceName): array
    {
        $normalizedKey = strtolower(trim($referenceName));
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

    private function namesEquivalent(string $left, string $right): bool
    {
        return $this->normalizeName($left) === $this->normalizeName($right);
    }

    private function normalizeName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+in\s+bb\s*$/', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

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
