<?php

namespace Tests\Unit;

use App\Services\X32\X32ChannelBusSendOscDecoder;
use App\Services\X32\X32OscAddressMap;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32ChannelBusSendOscDecoderTest extends TestCase
{
    #[Test]
    public function it_decodes_send_pan_and_tap_type_enums(): void
    {
        $normalized = X32ChannelBusSendOscDecoder::encodePan(0.0);

        $this->assertEqualsWithDelta(0.0, X32ChannelBusSendOscDecoder::decodePan($normalized), 0.1);
        $this->assertSame('post_fader', X32ChannelBusSendOscDecoder::typeToTap(4));
        $this->assertSame('pre_fader', X32ChannelBusSendOscDecoder::typeToTap(3));
    }

    #[Test]
    public function it_reports_odd_bus_send_pan_and_type_availability(): void
    {
        $this->assertTrue(X32ChannelBusSendOscDecoder::busSupportsSendPan(1));
        $this->assertFalse(X32ChannelBusSendOscDecoder::busSupportsSendPan(2));
        $this->assertTrue(X32ChannelBusSendOscDecoder::busSupportsSendType(15));
        $this->assertTrue(X32ChannelBusSendOscDecoder::busSupportsSendPanFollow(3));
        $this->assertFalse(X32ChannelBusSendOscDecoder::busSupportsSendPanFollow(1));
        $this->assertSame('/ch/01/mix/01/level', X32OscAddressMap::channelBusSendLevel(1, 1));
        $this->assertSame('/ch/01/mix/02/on', X32OscAddressMap::channelBusSendOn(1, 2));
    }
}
