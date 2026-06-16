<?php

namespace Tests\Unit;

use App\Services\X32\X32ConfigurationLearnAssembler;
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
    public function it_decodes_dca_membership_bitmap(): void
    {
        $this->assertSame([1, 3], X32ConfigurationLearnAssembler::decodeDcaMembershipBitmap(0b00000101));
    }
}
