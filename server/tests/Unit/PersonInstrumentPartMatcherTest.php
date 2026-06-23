<?php

namespace Tests\Unit;

use App\Support\PersonInstrumentPartMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonInstrumentPartMatcherTest extends TestCase
{
    private PersonInstrumentPartMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new PersonInstrumentPartMatcher;
    }

    #[DataProvider('altoSaxReferencePartPairs')]
    public function test_alto_sax_reference_matches_legitimate_alto_sax_parts(string $reference, string $part): void
    {
        $this->assertTrue($this->matcher->referenceMatchesPart($reference, $part));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function altoSaxReferencePartPairs(): array
    {
        return [
            'portal alto sax to catalog alto sax' => ['Alto Sax', 'Alto Sax'],
            'portal alto sax to catalog alto' => ['Alto Sax', 'Alto'],
            'alto saxophone variant' => ['Alto Saxophone', 'Alto Sax'],
            'sax alto variant' => ['Sax Alto', 'Alto Sax'],
            'eb alto sax variant' => ['Eb Alto Sax', 'Alto Sax in Eb'],
            'e flat alto sax variant' => ['E-flat Alto Sax', 'Alto Sax in E-flat'],
            'underscore part name' => ['Alto Sax', 'alto_sax'],
            'lowercase reference' => ['alto sax', 'Alto Sax'],
        ];
    }

    #[DataProvider('altoSaxNonMatchingParts')]
    public function test_alto_sax_reference_does_not_match_other_instruments(string $reference, string $part): void
    {
        $this->assertFalse($this->matcher->referenceMatchesPart($reference, $part));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function altoSaxNonMatchingParts(): array
    {
        return [
            'tenor sax' => ['Alto Sax', 'Tenor Sax'],
            'baritone sax' => ['Alto Sax', 'Baritone Sax'],
            'bari shorthand' => ['Alto Sax', 'Bari'],
            'trumpet' => ['Alto Sax', 'Trumpet'],
            'guitar' => ['Alto Sax', 'Guitar'],
            'bass' => ['Alto Sax', 'Bass'],
            'drums' => ['Alto Sax', 'Drums'],
            'vocals' => ['Alto Sax', 'Vocals'],
        ];
    }

    public function test_tenor_sax_reference_matches_tenor_sax_part(): void
    {
        $this->assertTrue($this->matcher->referenceMatchesPart('Tenor Sax', 'Tenor Sax'));
        $this->assertTrue($this->matcher->referenceMatchesPart('Tenor Saxophone', 'Tenor Sax in Bb'));
        $this->assertFalse($this->matcher->referenceMatchesPart('Tenor Sax', 'Alto Sax'));
        $this->assertFalse($this->matcher->referenceMatchesPart('Tenor Sax', 'Baritone Sax'));
    }

    public function test_baritone_sax_reference_matches_baritone_and_bari_parts(): void
    {
        $this->assertTrue($this->matcher->referenceMatchesPart('Baritone Sax', 'Baritone Sax'));
        $this->assertTrue($this->matcher->referenceMatchesPart('Baritone Sax', 'Bari'));
        $this->assertFalse($this->matcher->referenceMatchesPart('Baritone Sax', 'Alto Sax'));
    }

    public function test_generic_sax_reference_does_not_match_every_sax_family(): void
    {
        $this->assertFalse($this->matcher->referenceMatchesPart('Sax', 'Alto Sax'));
        $this->assertFalse($this->matcher->referenceMatchesPart('Saxophone', 'Tenor Sax'));
    }
}
