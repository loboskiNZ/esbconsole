<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mirrors x32-monitors-group-trim.js — relative group fader trim behaviour.
 */
class X32MonitorsGroupTrimTest extends TestCase
{
    private const SEND_DB_MIN = -90.0;

    private const SEND_DB_MAX = 10.0;

    #[Test]
    public function group_trim_applies_relative_db_offsets_not_absolute_values(): void
    {
        $baseline = [
            1 => -10.0,
            2 => -14.0,
            3 => -18.0,
        ];

        $trimmed = $this->applyGroupTrimLevels($baseline, 3.0);

        $this->assertEqualsWithDelta(-7.0, $trimmed[1], 0.01);
        $this->assertEqualsWithDelta(-11.0, $trimmed[2], 0.01);
        $this->assertEqualsWithDelta(-15.0, $trimmed[3], 0.01);
    }

    #[Test]
    public function group_trim_preserves_relative_differences_between_channels(): void
    {
        $baseline = [
            1 => -10.0,
            2 => -14.0,
            3 => -18.0,
        ];

        $afterPlusThree = $this->applyGroupTrimLevels($baseline, 3.0);
        $afterMinusTwo = $this->applyGroupTrimLevels($baseline, -2.0);

        $this->assertEqualsWithDelta(4.0, $afterPlusThree[1] - $afterPlusThree[2], 0.01);
        $this->assertEqualsWithDelta(4.0, $afterPlusThree[2] - $afterPlusThree[3], 0.01);
        $this->assertEqualsWithDelta(-7.0, $afterPlusThree[1], 0.01);
        $this->assertEqualsWithDelta(-12.0, $afterMinusTwo[1], 0.01);
        $this->assertEqualsWithDelta(-16.0, $afterMinusTwo[2], 0.01);
        $this->assertEqualsWithDelta(-20.0, $afterMinusTwo[3], 0.01);
    }

    #[Test]
    public function group_trim_clamps_each_channel_at_supported_send_range(): void
    {
        $baseline = [
            1 => -95.0,
            2 => 8.0,
        ];

        $trimmed = $this->applyGroupTrimLevels($baseline, 5.0);

        $this->assertSame(self::SEND_DB_MIN, $trimmed[1]);
        $this->assertSame(self::SEND_DB_MAX, $trimmed[2]);
    }

    #[Test]
    public function group_fader_display_starts_at_average_member_level(): void
    {
        $baseline = [
            1 => 0.0,
            2 => -10.0,
        ];

        $average = $this->averageBaselineDb($baseline);
        $display = $this->groupFaderDisplayDb($average, 0.0);

        $this->assertEqualsWithDelta(-5.0, $average, 0.01);
        $this->assertEqualsWithDelta(-5.0, $display, 0.01);
    }

    #[Test]
    public function group_fader_drag_converts_display_position_to_trim_offset_from_average(): void
    {
        $baseline = [
            1 => 0.0,
            2 => -10.0,
        ];

        $average = $this->averageBaselineDb($baseline);
        $trim = $this->trimOffsetFromGroupFaderDb(-2.0, $average);
        $trimmed = $this->applyGroupTrimLevels($baseline, $trim);

        $this->assertEqualsWithDelta(3.0, $trim, 0.01);
        $this->assertEqualsWithDelta(3.0, $trimmed[1], 0.01);
        $this->assertEqualsWithDelta(-7.0, $trimmed[2], 0.01);
        $this->assertEqualsWithDelta(-2.0, $this->groupFaderDisplayDb($average, $trim), 0.01);
    }

    private function averageBaselineDb(array $baselineByChannel): float
    {
        if ($baselineByChannel === []) {
            return 0.0;
        }

        return array_sum($baselineByChannel) / count($baselineByChannel);
    }

    private function groupFaderDisplayDb(float $averageBaselineDb, float $trimOffsetDb): float
    {
        return $averageBaselineDb + $trimOffsetDb;
    }

    private function trimOffsetFromGroupFaderDb(float $groupFaderDb, float $averageBaselineDb): float
    {
        return $groupFaderDb - $averageBaselineDb;
    }

    /**
     * @param  array<int, float>  $baselineByChannel
     * @return array<int, float>
     */
    private function applyGroupTrimLevels(array $baselineByChannel, float $trimOffsetDb): array
    {
        $levels = [];

        foreach ($baselineByChannel as $channel => $baselineDb) {
            $levels[$channel] = $this->clampSendLevelDb($baselineDb + $trimOffsetDb);
        }

        return $levels;
    }

    private function clampSendLevelDb(float $db): float
    {
        return min(self::SEND_DB_MAX, max(self::SEND_DB_MIN, $db));
    }
}
