<?php

namespace Tests\Unit;

use App\Support\BandStyles;
use PHPUnit\Framework\TestCase;

class BandStylesTest extends TestCase
{
    public function test_normalizes_comma_and_newline_separated_styles(): void
    {
        $this->assertSame(['Ska', 'Latin', 'Rock'], BandStyles::normalize("Ska\nLatin, Rock"));
    }

    public function test_to_input_value_renders_one_style_per_line(): void
    {
        $this->assertSame("Ska\nLatin", BandStyles::toInputValue(['Ska', 'Latin']));
    }
}
