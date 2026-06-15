<?php

namespace Tests\Unit;

use App\Services\X32\X32ChannelColorMap;
use PHPUnit\Framework\TestCase;

class X32ChannelColorMapTest extends TestCase
{
    public function test_resolves_standard_x32_color_indices(): void
    {
        $red = X32ChannelColorMap::resolve(1);
        $this->assertSame('Red', $red['label']);
        $this->assertSame('#c03030', $red['css']);
        $this->assertSame('#ffffff', $red['text']);
    }

    public function test_normalizes_blink_indices_to_base_palette(): void
    {
        $this->assertSame(
            X32ChannelColorMap::cssColor(1),
            X32ChannelColorMap::cssColor(9),
        );
    }

    public function test_off_color_for_missing_index(): void
    {
        $this->assertSame('#3f3f46', X32ChannelColorMap::cssColor(null));
    }
}
