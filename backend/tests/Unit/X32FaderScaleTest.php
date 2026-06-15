<?php

namespace Tests\Unit;

use App\Services\X32\X32FaderScale;
use PHPUnit\Framework\TestCase;

class X32FaderScaleTest extends TestCase
{
    public function test_unity_gain_at_seventy_five_percent_linear(): void
    {
        $this->assertEqualsWithDelta(0.0, X32FaderScale::linearToDb(0.75), 0.001);
        $this->assertEqualsWithDelta(0.75, X32FaderScale::dbToLinear(0.0), 0.001);
    }

    public function test_fader_range_endpoints(): void
    {
        $this->assertEqualsWithDelta(10.0, X32FaderScale::linearToDb(1.0), 0.001);
        $this->assertEqualsWithDelta(-60.0, X32FaderScale::linearToDb(0.0625), 0.001);
        $this->assertEqualsWithDelta(-90.0, X32FaderScale::linearToDb(0.0), 0.001);
    }

    public function test_db_to_linear_round_trip_at_crosspoints(): void
    {
        foreach ([-60.0, -30.0, -10.0, 0.0, 5.0, 10.0] as $db) {
            $linear = X32FaderScale::dbToLinear($db);
            $this->assertEqualsWithDelta($db, X32FaderScale::linearToDb($linear), 0.05, "Round trip failed for {$db} dB");
        }
    }

    public function test_format_db_display(): void
    {
        $this->assertSame('0.0', X32FaderScale::formatDb(0.0));
        $this->assertSame('+5.2', X32FaderScale::formatDb(5.19));
        $this->assertSame('-12.3', X32FaderScale::formatDb(-12.34));
        $this->assertSame('−∞', X32FaderScale::formatDb(-90.0));
    }

    public function test_scale_marks_follow_x32_crosspoints(): void
    {
        $marks = collect(X32FaderScale::scaleMarks())->keyBy('db');

        $this->assertSame(75.0, X32FaderScale::linearMarkPercent($marks[0]['linear']));
        $this->assertSame(100.0, X32FaderScale::linearMarkPercent($marks[10]['linear']));
        $this->assertEqualsWithDelta(6.25, X32FaderScale::linearMarkPercent($marks[-60]['linear']), 0.01);
        $this->assertTrue($marks[0]['unity'] ?? false);
    }

    public function test_quantize_linear_snaps_to_x32_step_grid(): void
    {
        $this->assertEqualsWithDelta(768 / 1023.0, X32FaderScale::quantizeLinear(0.75), 0.0001);
        $this->assertEqualsWithDelta(767 / 1023.0, X32FaderScale::quantizeLinear(0.749), 0.0001);
    }
}
