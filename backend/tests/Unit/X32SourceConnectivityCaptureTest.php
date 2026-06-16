<?php

namespace Tests\Unit;

use App\Services\X32\X32SourceConnectivityCapture;
use App\Services\X32\X32SourceConnectivityOscAddressMap;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32SourceConnectivityCaptureTest extends TestCase
{
    #[Test]
    public function it_reports_card_usb_online_when_xcard_type_is_present(): void
    {
        $capture = new X32SourceConnectivityCapture;

        $result = $capture->captureFromRawValues('test', [
            X32SourceConnectivityOscAddressMap::XCARD_TYPE => 2,
            X32SourceConnectivityOscAddressMap::AES50_A => '',
            X32SourceConnectivityOscAddressMap::AES50_B => '',
            X32SourceConnectivityOscAddressMap::AES50_STATE => 0,
        ]);

        $this->assertSame('online', $result['normalized']['ableton']['state']);
        $this->assertSame('Online', $result['normalized']['ableton']['label']);
        $this->assertSame('X-USB', $result['normalized']['ableton']['detail']);
    }

    #[Test]
    public function it_reports_aes50_ports_online_when_chain_is_detected(): void
    {
        $capture = new X32SourceConnectivityCapture;

        $result = $capture->captureFromRawValues('test', [
            X32SourceConnectivityOscAddressMap::AES50_A => 'Es32',
            X32SourceConnectivityOscAddressMap::AES50_B => 'Cs32',
            X32SourceConnectivityOscAddressMap::AES50_STATE => 0,
            X32SourceConnectivityOscAddressMap::XCARD_TYPE => 0,
        ]);

        $this->assertSame('online', $result['normalized']['stagebox_a']['state']);
        $this->assertSame('online', $result['normalized']['stagebox_b']['state']);
    }

    #[Test]
    public function it_reports_aes50_offline_when_audio_error_bit_is_set(): void
    {
        $capture = new X32SourceConnectivityCapture;

        $result = $capture->captureFromRawValues('test', [
            X32SourceConnectivityOscAddressMap::AES50_A => 'Es32',
            X32SourceConnectivityOscAddressMap::AES50_B => '',
            X32SourceConnectivityOscAddressMap::AES50_STATE => 1,
            X32SourceConnectivityOscAddressMap::XCARD_TYPE => 2,
        ]);

        $this->assertSame('offline', $result['normalized']['stagebox_a']['state']);
        $this->assertSame('online', $result['normalized']['ableton']['state']);
    }
}
