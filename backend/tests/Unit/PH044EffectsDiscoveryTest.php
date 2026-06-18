<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PH044.01 — lightweight guards for documented effects discovery artefacts.
 *
 * @see docs/x32/PH044_EFFECTS_DISCOVERY_AUDIT.md
 * @see docs/x32/PH044_EFFECTS_ALGORITHM_CATALOGUE.md
 */
class PH044EffectsDiscoveryTest extends TestCase
{
    private function docPath(string $relative): string
    {
        return dirname(base_path()).'/docs/x32/'.$relative;
    }

    private function readDoc(string $relative): string
    {
        $path = $this->docPath($relative);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    #[Test]
    public function effects_discovery_audit_exists_with_required_sections(): void
    {
        $content = $this->readDoc('PH044_EFFECTS_DISCOVERY_AUDIT.md');

        foreach ([
            'FX Rack Architecture',
            'Algorithm Catalogue',
            'Parameter Discovery',
            'Routing Discovery',
            'Runtime Safety Audit',
            'Package Feasibility',
            'Gaps Requiring Live Console Verification',
            'PH044.01 Constraints Confirmed',
        ] as $section) {
            $this->assertStringContainsString($section, $content, "Missing audit section: {$section}");
        }
    }

    #[Test]
    public function algorithm_catalogue_exists_with_slot_group_tables(): void
    {
        $content = $this->readDoc('PH044_EFFECTS_ALGORITHM_CATALOGUE.md');

        $this->assertStringContainsString('FX1–FX4 Algorithms', $content);
        $this->assertStringContainsString('FX5–FX8 Algorithms', $content);
        $this->assertStringContainsString('| Enum | Code | Name | Category |', $content);
    }

    #[Test]
    public function known_discovered_algorithms_have_ids_and_names(): void
    {
        $content = $this->readDoc('PH044_EFFECTS_ALGORITHM_CATALOGUE.md');

        foreach ([
            '| 0 | HALL | Hall Reverb |',
            '| 5 | PLAT | Plate Reverb |',
            '| 10 | DLY | Stereo Delay |',
            '| 26 | MODD | Modulation Delay |',
            '| 28 | GEQ | Stereo Graphic EQ |',
            '| 1 | GEQ | Stereo Graphic EQ |', // FX5-8 enum 1
        ] as $row) {
            $this->assertStringContainsString($row, $content, "Missing catalogue row: {$row}");
        }
    }

    #[Test]
    public function safety_classifications_include_required_actions(): void
    {
        $content = $this->readDoc('PH044_EFFECTS_DISCOVERY_AUDIT.md');

        foreach ([
            'Algorithm change',
            'Parameter change',
            'FX send level change',
            'FX return level change',
            'FX return mute',
            'FX routing change',
            'FX insert assignment',
            'Main FOH graphic EQ',
        ] as $action) {
            $this->assertStringContainsString($action, $content, "Missing safety action: {$action}");
        }

        $this->assertStringContainsString('SAFE DURING SONG', $content);
        $this->assertStringContainsString('SAFE BETWEEN SONGS', $content);
        $this->assertStringContainsString('NOT RECOMMENDED LIVE', $content);
        $this->assertStringContainsString(
            'Effects algorithm changes must not be performed during an active song',
            $content,
        );
    }

    #[Test]
    public function decision_log_contains_ph044_effects_operating_decision(): void
    {
        $content = $this->readDoc('DECISION_LOG.md');

        $this->assertStringContainsString('X32-DEC-006', $content);
        $this->assertStringContainsString('show/song-aware effect packages', $content);
        $this->assertStringContainsString('between-song or explicit transition-cue operations only', $content);
        $this->assertStringContainsString('Algorithm ID', $content);
        $this->assertStringContainsString('FX slot', $content);
        $this->assertStringContainsString('PH044.01', $content);
    }
}
