<?php

namespace Tests\Unit;

use App\Services\Console\ShowConsoleStripEnricher;
use PHPUnit\Framework\TestCase;

class ShowConsoleStripEnricherTest extends TestCase
{
    public function test_adds_osc_paths_when_missing_from_legacy_baseline_rows(): void
    {
        $enricher = new ShowConsoleStripEnricher;

        $strips = $enricher->enrich([
            [
                'index' => 1,
                'name' => 'CH 01 Scene 01',
                'fader' => 0.5,
            ],
        ], 'channel');

        $this->assertSame('/ch/01/mix/fader', $strips[0]['osc_fader']);
        $this->assertSame('/ch/01/mix/on', $strips[0]['osc_on']);
    }

    public function test_preserves_existing_osc_paths(): void
    {
        $enricher = new ShowConsoleStripEnricher;

        $strips = $enricher->enrich([
            [
                'index' => 2,
                'osc_fader' => '/custom/fader',
                'osc_on' => '/custom/on',
            ],
        ], 'channel');

        $this->assertSame('/custom/fader', $strips[0]['osc_fader']);
        $this->assertSame('/custom/on', $strips[0]['osc_on']);
    }

    public function test_merges_color_and_name_from_raw_snapshot_osc_responses(): void
    {
        $enricher = new ShowConsoleStripEnricher;

        $snapshot = new \App\Models\ConsoleLearningSnapshot([
            'raw_snapshot_json' => [
                'osc_responses' => [
                    ['path' => '/ch/03/config/name', 'value' => 'Snare'],
                    ['path' => '/ch/03/config/color', 'value' => 1],
                    ['path' => '/ch/03/mix/fader', 'value' => 0.72],
                ],
            ],
        ]);

        $strips = $enricher->enrich([
            [
                'index' => 3,
                'fader' => 0.5,
            ],
        ], 'channel', $snapshot);

        $this->assertSame('Snare', $strips[0]['name']);
        $this->assertSame(1, $strips[0]['color']);
        $this->assertSame(0.5, $strips[0]['fader']);
    }

    public function test_detects_incomplete_metadata(): void
    {
        $enricher = new ShowConsoleStripEnricher;

        $this->assertTrue($enricher->metadataIncomplete([
            ['index' => 1, 'name' => 'CH 01 Scene 01', 'fader' => 0.5],
        ]));

        $this->assertFalse($enricher->metadataIncomplete([
            ['index' => 1, 'name' => 'Kick', 'color' => 1, 'fader' => 0.5],
        ]));
    }
}
