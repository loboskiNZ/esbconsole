<?php

namespace Tests\Unit;

use App\Services\X32\X32ChannelBusSendOscDecoder;
use App\Services\X32\X32FaderScale;
use App\Services\X32\X32InputChannelControlMap;
use App\Services\X32\X32MonitorSendMatrixLearnCapture;
use App\Services\X32\X32OscAddressMap;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PH043.06 — documents verified read-path facts for future monitor send writes.
 *
 * @see docs/x32/PH043_CONFIGURATION_DISCOVERY_AUDIT.md § PH043.06
 */
class X32MonitorSendControlReadinessTest extends TestCase
{
    #[Test]
    public function send_level_uses_documented_fader_scale_round_trip(): void
    {
        foreach ([-60.0, -30.0, -10.0, 0.0, 5.0, 10.0] as $db) {
            $linear = X32FaderScale::dbToLinear($db);
            $quantized = X32FaderScale::quantizeLinear($linear);

            $this->assertEqualsWithDelta(
                $db,
                X32FaderScale::linearToDb($quantized),
                0.05,
                "Send level scale round trip failed for {$db} dB",
            );
        }

        $this->assertSame('/ch/14/mix/05/level', X32OscAddressMap::channelBusSendLevel(14, 5));
    }

    #[Test]
    public function send_on_enum_is_stable_without_channel_mute_invert(): void
    {
        $fixture = X32MonitorSendMatrixLearnCapture::fixtureChannelOne();

        $this->assertSame(1, $fixture['buses'][1]['on']);
        $this->assertSame(0, $fixture['buses'][3]['on']);
        $this->assertSame('/ch/01/mix/01/on', X32OscAddressMap::channelBusSendOn(1, 1));

        $muteDefinition = X32InputChannelControlMap::definition('mute');
        $this->assertTrue($muteDefinition['invert_osc'] ?? false);
        $this->assertNotSame(
            X32InputChannelControlMap::oscPath('mute', 1),
            X32OscAddressMap::channelBusSendOn(1, 1),
        );
    }

    #[Test]
    public function send_pan_scale_is_stable_for_odd_buses(): void
    {
        foreach ([-100.0, -50.0, 0.0, 50.0, 100.0] as $pan) {
            $normalized = X32ChannelBusSendOscDecoder::encodePan($pan);

            $this->assertEqualsWithDelta(
                $pan,
                X32ChannelBusSendOscDecoder::decodePan($normalized),
                2.1,
            );
        }

        $this->assertTrue(X32ChannelBusSendOscDecoder::busSupportsSendPan(7));
        $this->assertSame('/ch/01/mix/07/pan', X32OscAddressMap::channelBusSendPan(1, 7));
    }

    #[Test]
    public function send_type_tap_enum_mapping_is_stable_for_odd_buses(): void
    {
        $expected = [
            0 => 'in_lc',
            1 => 'pre_eq',
            2 => 'post_eq',
            3 => 'pre_fader',
            4 => 'post_fader',
            5 => 'grp',
        ];

        foreach ($expected as $type => $tap) {
            $this->assertSame($tap, X32ChannelBusSendOscDecoder::typeToTap($type));
        }

        $this->assertSame('/ch/01/mix/15/type', X32OscAddressMap::channelBusSendType(1, 15));
    }

    #[Test]
    public function even_bus_pan_and_type_paths_are_absent_from_capture(): void
    {
        $capture = (new X32MonitorSendMatrixLearnCapture)->captureSend(
            1,
            2,
            fn (): float => 0.5,
            fn (): int => 1,
        );

        $this->assertArrayNotHasKey('pan', $capture);
        $this->assertArrayNotHasKey('type', $capture);
        $this->assertArrayNotHasKey('tap', $capture);
        $this->assertArrayHasKey('level', $capture);
        $this->assertArrayHasKey('on', $capture);
        $this->assertFalse(X32ChannelBusSendOscDecoder::busSupportsSendPan(2));
        $this->assertFalse(X32ChannelBusSendOscDecoder::busSupportsSendType(2));
    }

    #[Test]
    public function pan_follow_is_only_queried_for_odd_buses_from_three_upward(): void
    {
        $this->assertFalse(X32ChannelBusSendOscDecoder::busSupportsSendPanFollow(1));
        $this->assertTrue(X32ChannelBusSendOscDecoder::busSupportsSendPanFollow(3));
        $this->assertSame('/ch/01/mix/03/panFollow', X32OscAddressMap::channelBusSendPanFollow(1, 3));
    }

    #[Test]
    public function no_monitor_send_write_controls_exist_in_channel_control_map(): void
    {
        foreach (['send_level', 'send_on', 'send_pan', 'send_tap', 'monitor_send'] as $key) {
            $this->assertNull(
                X32InputChannelControlMap::definition($key),
                "Unexpected monitor send control map entry: {$key}",
            );
        }

        $this->assertFalse(class_exists(\App\Services\Console\MonitorSendControlService::class));
    }
}
