<?php

namespace Tests\Unit;

use App\Services\Console\VirtualConsoleStripBuilder;
use App\Services\X32\X32InputChannelControlMap;
use App\Services\X32\X32OscAddressMap;
use PHPUnit\Framework\TestCase;

class X32InputChannelControlMapTest extends TestCase
{
    public function test_fader_osc_path_for_channel_one(): void
    {
        $this->assertSame('/ch/01/mix/fader', X32InputChannelControlMap::oscPath('fader', 1));
    }

    public function test_headamp_dependent_controls_have_no_channel_osc_path(): void
    {
        $this->assertNull(X32InputChannelControlMap::oscPath('gain', 1));
        $this->assertNull(X32InputChannelControlMap::oscPath('phantom48v', 1));
    }

    public function test_mute_definition_notes_inverted_osc(): void
    {
        $definition = X32InputChannelControlMap::definition('mute');
        $this->assertTrue($definition['invert_osc'] ?? false);
    }

    public function test_main_lr_uses_x32_mix_st_path(): void
    {
        $this->assertSame('/ch/01/mix/st', X32InputChannelControlMap::oscPath('main_lr', 1));
        $this->assertSame('/ch/01/mix/st', X32OscAddressMap::channelLr(1));
    }
}

class VirtualConsoleStripBuilderTest extends TestCase
{
    public function test_builds_exactly_thirty_two_strips(): void
    {
        $builder = new VirtualConsoleStripBuilder(new \App\Services\Console\ShowConsoleStripEnricher);
        $strips = $builder->build([
            'channels' => [
                ['index' => 1, 'name' => 'Kick', 'fader' => 0.75, 'mute' => false, 'color' => 1],
            ],
        ]);

        $this->assertCount(32, $strips);
        $this->assertSame(1, $strips[0]->channelNumber);
        $this->assertSame('Kick', $strips[0]->name);
        $this->assertSame('2', $strips[1]->name);
        $this->assertSame(32, $strips[31]->channelNumber);
    }

    public function test_loads_learned_control_state_into_strips(): void
    {
        $builder = new VirtualConsoleStripBuilder(new \App\Services\Console\ShowConsoleStripEnricher);
        $strips = $builder->build([
            'channels' => [
                [
                    'index' => 1,
                    'name' => 'Kick',
                    'fader' => 0.75,
                    'mute' => true,
                    'color' => 2,
                    'controls' => [
                        'gate_on' => true,
                        'compressor_on' => false,
                        'eq_on' => true,
                        'sends_open' => true,
                        'gain' => 0.62,
                        'stereo_link' => true,
                        'main_lr' => false,
                        'pan' => 0.35,
                    ],
                ],
            ],
        ]);

        $strip = $strips[0];
        $this->assertTrue($strip->muted);
        $this->assertTrue($strip->gateOn);
        $this->assertFalse($strip->compressorOn);
        $this->assertTrue($strip->eqOn);
        $this->assertTrue($strip->sendsOpen);
        $this->assertSame(0.62, $strip->gain);
        $this->assertTrue($strip->linked);
        $this->assertFalse($strip->mainLr);
        $this->assertSame(0.35, $strip->pan);
        $this->assertSame(0.75, $strip->faderLevel);
    }
}
