<?php

namespace Tests\Unit;

use App\Support\PerformanceEventTime;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PerformanceEventTimeTest extends TestCase
{
    public function test_daylight_saving_zones_follow_iana_rules_and_leave_the_instant_unchanged(): void
    {
        $winter = Carbon::parse('2026-01-15 17:17:00', 'UTC');
        $summer = Carbon::parse('2026-07-15 17:17:00', 'UTC');

        $this->assertSame('15 Jan 2026, 12:17', PerformanceEventTime::format($winter, 'America/New_York'));
        $this->assertSame('15 Jul 2026, 13:17', PerformanceEventTime::format($summer, 'America/New_York'));
        $this->assertSame('15 Jan 2026, 18:17', PerformanceEventTime::format($winter, 'Europe/Madrid'));
        $this->assertSame('15 Jul 2026, 19:17', PerformanceEventTime::format($summer, 'Europe/Madrid'));

        $this->assertSame('UTC', $winter->timezoneName);
        $this->assertSame('2026-01-15 17:17:00', $winter->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $summer->timezoneName);
        $this->assertSame('2026-07-15 17:17:00', $summer->format('Y-m-d H:i:s'));
    }

    public function test_blank_or_unrecognised_timezone_uses_utc_and_null_instants_stay_blank(): void
    {
        $instant = Carbon::parse('2026-01-15 17:17:00', 'UTC');

        $this->assertSame('America/New_York', PerformanceEventTime::identifier('America/New_York'));
        $this->assertSame('UTC', PerformanceEventTime::identifier(null));
        $this->assertSame('UTC', PerformanceEventTime::identifier(''));
        $this->assertSame('UTC', PerformanceEventTime::identifier('Not/AZone'));
        $this->assertSame('UTC', PerformanceEventTime::identifier('+12:00'));
        $this->assertSame('15 Jan 2026, 17:17', PerformanceEventTime::format($instant, null));
        $this->assertSame('', PerformanceEventTime::format(null, 'Europe/Madrid'));
        $this->assertSame('UTC', $instant->timezoneName);
    }
}
