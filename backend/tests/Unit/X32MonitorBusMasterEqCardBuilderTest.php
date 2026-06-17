<?php

namespace Tests\Unit;

use App\Services\Console\X32MonitorBusMasterEqCardBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32MonitorBusMasterEqCardBuilderTest extends TestCase
{
    private X32MonitorBusMasterEqCardBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new X32MonitorBusMasterEqCardBuilder;
    }

    #[Test]
    public function it_builds_six_x32_bus_eq_bands_with_mode_options_and_names(): void
    {
        $card = $this->builder->build('Ed IEM', ['number' => 1]);

        $this->assertCount(6, $card['bands']);
        $this->assertSame(['HCUT', 'HSHV', 'VEQ', 'PEQ', 'LSHV', 'LCUT'], $card['mode_options']);
        $this->assertSame('HCUT', $card['bands'][0]['mode']);
        $this->assertSame('LCUT', $card['bands'][5]['mode']);
        $this->assertSame('Low', $card['bands'][0]['short_name']);
        $this->assertSame('high', $card['bands'][5]['short_name']);
    }

    #[Test]
    public function it_seeds_placeholder_scaffold_defaults_for_frequency_gain_and_q(): void
    {
        $card = $this->builder->build('Ed IEM', ['number' => 1]);

        $this->assertFalse($card['learned']);
        $this->assertSame('79.6', $card['bands'][0]['frequency_input']);
        $this->assertSame('1K97', $card['bands'][3]['frequency_input']);
        $this->assertSame('10K02', $card['bands'][5]['frequency_input']);
        $this->assertSame(0.0, $card['bands'][0]['gain_db']);
        $this->assertSame('0', $card['bands'][0]['gain_input']);
        $this->assertSame('2', $card['bands'][2]['q_input']);
        $this->assertCount(6, $card['graph']['band_nodes']);
        $this->assertSame(0.0, $card['graph']['band_nodes'][0]['gain_db']);
    }

    #[Test]
    public function it_toggles_visible_fields_by_mode(): void
    {
        $card = $this->builder->build('Ed IEM', [
            'number' => 1,
            'eq' => [
                'on' => ['value' => true, 'state' => 'learned'],
                'bands' => [
                    ['number' => 1, 'mode' => ['value' => 'LCUT', 'state' => 'learned'], 'frequency_hz' => ['value' => 80, 'state' => 'learned']],
                    ['number' => 2, 'mode' => ['value' => 'LSHV', 'state' => 'learned'], 'frequency_hz' => ['value' => 120, 'state' => 'learned'], 'gain_db' => ['value' => 2.0, 'state' => 'learned']],
                    ['number' => 3, 'mode' => ['value' => 'PEQ', 'state' => 'learned'], 'frequency_hz' => ['value' => 500, 'state' => 'learned'], 'gain_db' => ['value' => -1.0, 'state' => 'learned'], 'q' => ['value' => 2.2, 'state' => 'learned']],
                ],
            ],
        ]);

        $this->assertTrue($card['bands'][0]['frequency_visible']);
        $this->assertFalse($card['bands'][0]['gain_visible']);
        $this->assertFalse($card['bands'][0]['q_visible']);

        $this->assertTrue($card['bands'][1]['gain_visible']);
        $this->assertFalse($card['bands'][1]['q_visible']);

        $this->assertTrue($card['bands'][2]['q_visible']);
        $this->assertSame('2.20', $card['bands'][2]['q_input']);
    }

    #[Test]
    public function it_marks_placeholder_eq_honestly_while_still_showing_scaffold_defaults(): void
    {
        $card = $this->builder->build('Ed IEM', ['number' => 1]);

        $this->assertFalse($card['learned']);
        $this->assertSame(
            'Placeholder EQ display only. Values shown are not from the X32.',
            $card['placeholder_notice'],
        );
        $this->assertTrue($card['bands'][0]['is_placeholder']);
        $this->assertFalse($card['graph']['uses_learned_points']);
    }

    #[Test]
    public function it_maps_legacy_low_cut_to_band_six_lcut(): void
    {
        $card = $this->builder->build('Ed IEM', [
            'number' => 1,
            'eq' => [
                'on' => ['value' => true, 'state' => 'learned'],
                'low_cut' => [
                    'on' => ['value' => true, 'state' => 'learned'],
                    'frequency_hz' => ['value' => 100, 'state' => 'learned'],
                    'mode' => ['value' => 'Low Cut', 'state' => 'learned'],
                ],
            ],
        ]);

        $this->assertSame('LCUT', $card['bands'][5]['mode']);
        $this->assertSame('100 Hz', $card['bands'][5]['frequency_display']);
    }

    #[Test]
    public function it_builds_visual_approximation_graph_labels_without_dsp_claims(): void
    {
        $card = $this->builder->build('Ed IEM', ['number' => 1]);

        $this->assertSame(['20Hz', '100Hz', '1kHz', '10kHz', '20kHz'], $card['graph']['frequency_labels']);
        $this->assertSame('Visual approximation only — not DSP accurate.', $card['graph']['disclaimer']);
        $this->assertStringContainsString('X32-style bus master EQ layout', $card['layout_note']);
    }
}
