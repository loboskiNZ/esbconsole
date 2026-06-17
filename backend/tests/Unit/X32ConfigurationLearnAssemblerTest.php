<?php

namespace Tests\Unit;

use App\Services\X32\X32BusEqLearnCapture;
use App\Services\X32\X32ConfigurationLearnAssembler;
use App\Services\X32\X32MonitorSendMatrixLearnCapture;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32ConfigurationLearnAssemblerTest extends TestCase
{
    #[Test]
    public function it_builds_configuration_block_from_fixture_summary(): void
    {
        $configuration = app(X32ConfigurationLearnAssembler::class)->build([
            'transport' => 'fake_fixture',
            'console_type' => 'x32',
            'device_key' => 'foh-x32',
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'channels' => [
                [
                    'index' => 1,
                    'name' => 'Kick',
                    'color' => 1,
                    'fader' => 0.63,
                    'mute' => false,
                    'controls' => ['pan' => 0.5, 'main_lr' => true],
                ],
            ],
            'buses' => [
                ['index' => 1, 'name' => 'Ed IEM', 'fader' => 0.5, 'mute' => false, 'color' => 3],
            ],
            'dcas' => [
                ['index' => 1, 'name' => 'DCA 1', 'fader' => 0.5, 'mute' => false],
            ],
            'matrices' => [
                ['index' => 1, 'name' => 'MTRX 1', 'fader' => 0.5, 'mute' => false],
            ],
            'fx' => [['slot' => 1, 'name' => 'FX 1']],
            'routing' => [
                'normalized' => [
                    'input_banks' => [[
                        'bank' => '1-8',
                        'console_channels' => [1, 2, 3, 4, 5, 6, 7, 8],
                        'source_type' => 'aes50_a',
                        'source_range' => 'A1-8',
                    ]],
                ],
            ],
        ]);

        $this->assertSame('fake_fixture', $configuration['source']);
        $this->assertSame('learned', $configuration['identity']['console_name']['state']);
        $this->assertSame('FOH X32', $configuration['identity']['console_name']['value']);
        $this->assertSame('not_learned', $configuration['identity']['scene_name']['state']);
        $this->assertCount(1, $configuration['channels']);
        $this->assertSame('learned', $configuration['channels'][0]['name']['state']);
        $this->assertSame('Kick', $configuration['channels'][0]['name']['value']);
        $this->assertSame('learned', $configuration['channels'][0]['source_reference']['state']);
        $this->assertSame('learned', $configuration['buses'][0]['purpose']['state']);
        $this->assertSame('not_learned', $configuration['dcas'][0]['name']['state']);
        $this->assertSame('not_learned', $configuration['matrices'][0]['name']['state']);
        $this->assertFalse($configuration['fx']['learned']);
        $this->assertSame('fixture_transport_not_configuration_learned', $configuration['fx']['reason']);
        $this->assertSame('not_learned', $configuration['globals']['sample_rate']['state']);
    }

    #[Test]
    public function it_attaches_configuration_and_removes_transient_capture(): void
    {
        $summary = app(X32ConfigurationLearnAssembler::class)->attach([
            'transport' => 'live_osc',
            'device_name' => 'Main X32',
            'device_key' => 'main-x32',
            'console_type' => 'x32',
            'scene_number' => '02',
            'scene_name' => 'Band Rehearsal',
            'channels' => [],
            'buses' => [],
            'dcas' => [],
            'matrices' => [],
            'fx' => [],
            'configuration_capture' => [
                'identity' => [
                    'sample_rate' => ['value' => '48K', 'state' => 'learned'],
                    'clock_source' => ['value' => 'INT', 'state' => 'learned'],
                    'firmware' => ['value' => null, 'state' => 'not_learned', 'reason' => 'info_query_not_implemented'],
                ],
            ],
        ]);

        $this->assertArrayHasKey('configuration', $summary);
        $this->assertArrayNotHasKey('configuration_capture', $summary);
        $this->assertSame('learned', $summary['configuration']['identity']['scene_name']['state']);
        $this->assertSame('48K', $summary['configuration']['globals']['sample_rate']['value']);
    }

    #[Test]
    public function it_marks_live_fx_as_not_implemented(): void
    {
        $configuration = app(X32ConfigurationLearnAssembler::class)->build([
            'transport' => 'live_osc',
            'channels' => [],
            'buses' => [],
            'dcas' => [],
            'matrices' => [],
            'fx' => [],
        ]);

        $this->assertFalse($configuration['fx']['learned']);
        $this->assertSame('not_implemented', $configuration['fx']['reason']);
        $this->assertSame([], $configuration['fx']['slots']);
    }

    #[Test]
    public function it_keeps_configuration_warnings_separate_from_routing_warnings(): void
    {
        $configuration = app(X32ConfigurationLearnAssembler::class)->build([
            'transport' => 'fake_fixture',
            'console_type' => 'x32',
            'device_key' => 'foh-x32',
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'channels' => [],
            'buses' => [],
            'dcas' => [],
            'matrices' => [],
            'fx' => [],
            'routing' => [
                'warnings' => [
                    'Input bank 1-8 has unknown routing index 99.',
                    'Console routswitch is PLAY — IN bank values reflect playback path; PLAY bank paths were not read in PH042.03.01.',
                ],
            ],
        ]);

        $this->assertSame(
            ['Configuration identity globals require live OSC transport.'],
            $configuration['warnings'],
        );
    }

    #[Test]
    public function it_decodes_dca_membership_bitmap(): void
    {
        $this->assertSame([1, 3], X32ConfigurationLearnAssembler::decodeDcaMembershipBitmap(0b00000101));
    }

    #[Test]
    public function it_stores_learned_bus_eq_under_configuration_buses(): void
    {
        $configuration = app(X32ConfigurationLearnAssembler::class)->build([
            'transport' => 'live_osc',
            'console_type' => 'x32',
            'device_key' => 'foh-x32',
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'channels' => [],
            'buses' => [
                [
                    'index' => 1,
                    'name' => 'Ed IEM',
                    'fader' => 0.5,
                    'mute' => false,
                    'color' => 3,
                    'eq' => X32BusEqLearnCapture::fixtureBusOne(),
                ],
                [
                    'index' => 2,
                    'name' => 'Bus 02',
                    'fader' => 0.5,
                    'mute' => false,
                    'color' => 3,
                ],
            ],
            'dcas' => [],
            'matrices' => [],
            'fx' => [],
        ]);

        $busOne = $configuration['buses'][0];
        $busTwo = $configuration['buses'][1];

        $this->assertArrayHasKey('eq', $busOne);
        $this->assertArrayNotHasKey('eq', $busTwo);
        $this->assertSame('learned', $busOne['eq']['on']['state']);
        $this->assertFalse($busOne['eq']['on']['value']);
        $this->assertSame('learned', $busOne['eq']['bands'][0]['frequency_hz']['state']);
        $this->assertEqualsWithDelta(79.6, $busOne['eq']['bands'][0]['frequency_hz']['value'], 0.1);
        $this->assertSame('LSHV', $busOne['eq']['bands'][0]['mode']['value']);
        $this->assertSame('learned', $busOne['eq']['bands'][0]['q']['state']);
        $this->assertEqualsWithDelta(1.96, $busOne['eq']['bands'][0]['q']['value'], 0.05);
        $this->assertSame('LCUT', $busOne['eq']['bands'][5]['mode']['value']);
    }

    #[Test]
    public function it_stores_learned_monitor_send_matrix_under_channel_configuration(): void
    {
        $configuration = app(X32ConfigurationLearnAssembler::class)->build([
            'transport' => 'live_osc',
            'console_type' => 'x32',
            'device_key' => 'foh-x32',
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'channels' => [
                [
                    'index' => 1,
                    'name' => 'Kick',
                    'fader' => 0.63,
                    'mute' => false,
                    'sends' => X32MonitorSendMatrixLearnCapture::fixtureChannelOne(),
                ],
            ],
            'buses' => [],
            'dcas' => [],
            'matrices' => [],
            'fx' => [],
        ]);

        $channel = $configuration['channels'][0];

        $this->assertArrayHasKey('sends', $channel);
        $this->assertSame('learned', $channel['sends']['buses']['1']['level']['state']);
        $this->assertSame('/ch/01/mix/01/level', $channel['sends']['buses']['1']['level']['source']);
        $this->assertEqualsWithDelta(0.0, $channel['sends']['buses']['1']['level']['value']['value'], 0.1);
        $this->assertTrue($channel['sends']['buses']['1']['on']['value']);
        $this->assertSame('learned', $channel['sends']['buses']['1']['tap']['state']);
        $this->assertSame('post_fader', $channel['sends']['buses']['1']['tap']['value']);
        $this->assertSame('learned', $channel['sends']['buses']['1']['pan']['state']);
        $this->assertSame('not_learned', $channel['sends']['buses']['2']['pan']['state']);
        $this->assertSame('osc_path_not_on_even_bus_send', $channel['sends']['buses']['2']['pan']['reason']);
    }

    #[Test]
    public function it_does_not_store_placeholder_sends_when_capture_is_missing(): void
    {
        $configuration = app(X32ConfigurationLearnAssembler::class)->build([
            'transport' => 'fake_fixture',
            'console_type' => 'x32',
            'device_key' => 'foh-x32',
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'channels' => [
                ['index' => 1, 'name' => 'Kick', 'fader' => 0.5, 'mute' => false],
            ],
            'buses' => [],
            'dcas' => [],
            'matrices' => [],
            'fx' => [],
        ]);

        $this->assertArrayNotHasKey('sends', $configuration['channels'][0]);
    }

    #[Test]
    public function it_does_not_store_placeholder_eq_when_capture_is_missing(): void
    {
        $configuration = app(X32ConfigurationLearnAssembler::class)->build([
            'transport' => 'fake_fixture',
            'console_type' => 'x32',
            'device_key' => 'foh-x32',
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'channels' => [],
            'buses' => [
                ['index' => 1, 'name' => 'Ed IEM', 'fader' => 0.5, 'mute' => false, 'color' => 3],
            ],
            'dcas' => [],
            'matrices' => [],
            'fx' => [],
        ]);

        $this->assertArrayNotHasKey('eq', $configuration['buses'][0]);
    }
}
