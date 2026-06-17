<?php

namespace Tests\Unit;

use App\Services\X32\X32OscAddressMap;
use App\Services\X32\X32OscMessageCodec;
use PHPUnit\Framework\TestCase;

class X32OscAddressMapTest extends TestCase
{
    public function test_channel_paths_follow_x32_convention(): void
    {
        $this->assertSame('/ch/01/mix/fader', X32OscAddressMap::channelFader(1));
        $this->assertSame('/ch/32/mix/on', X32OscAddressMap::channelOn(32));
    }

    public function test_parse_path_identifies_fader_and_mute_parameters(): void
    {
        $fader = X32OscAddressMap::parsePath('/ch/05/mix/fader');
        $this->assertSame('channels', $fader['layer']);
        $this->assertSame(5, $fader['index']);
        $this->assertSame('fader', $fader['parameter']);

        $mute = X32OscAddressMap::parsePath('/bus/03/mix/on');
        $this->assertSame('buses', $mute['layer']);
        $this->assertSame('mute', $mute['parameter']);
    }
}

class X32OscMessageCodecTest extends TestCase
{
    public function test_build_and_parse_float_round_trip(): void
    {
        $codec = new X32OscMessageCodec;
        $payload = $codec->buildFloat('/ch/01/mix/fader', 0.75);

        $this->assertSame(0.75, $codec->parseFloatResponse($payload));
    }

    public function test_parse_string_response(): void
    {
        $codec = new X32OscMessageCodec;
        $path = '/ch/01/config/name';
        $paddedPath = $path."\0".str_repeat("\0", (4 - ((strlen($path) + 1) % 4)) % 4);
        $payload = $paddedPath.',s'."\0\0\0".'Kick'."\0";

        $this->assertSame('Kick', $codec->parseStringResponse($payload));
    }

    public function test_parse_big_endian_float_from_x32_response(): void
    {
        $codec = new X32OscMessageCodec;
        $payload = hex2bin('2f63682f30312f6d69782f6661646572000000002c6600003f2e2b8b');

        $this->assertEqualsWithDelta(0.68035191297531, $codec->parseFloatResponse($payload), 0.0001);
    }

    public function test_parse_on_response_accepts_int_or_float_type_tags(): void
    {
        $codec = new X32OscMessageCodec;
        $path = '/ch/01/mix/01/on';
        $paddedPath = $path."\0".str_repeat("\0", (4 - ((strlen($path) + 1) % 4)) % 4);
        $intPayload = $paddedPath.',i'."\0\0\0".pack('N', 0);
        $floatPayload = $paddedPath.',f'."\0\0\0".pack('G', 1.0);

        $this->assertSame(0, $codec->parseOnResponse($intPayload));
        $this->assertSame(1, $codec->parseOnResponse($floatPayload));
    }
}
