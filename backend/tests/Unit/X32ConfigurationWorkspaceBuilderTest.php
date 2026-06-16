<?php

namespace Tests\Unit;

use App\Services\Console\X32ConfigurationWorkspaceBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32ConfigurationWorkspaceBuilderTest extends TestCase
{
    private X32ConfigurationWorkspaceBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new X32ConfigurationWorkspaceBuilder;
    }

    #[Test]
    public function it_builds_header_with_console_scene_and_name(): void
    {
        $workspace = $this->builder->build([
            'device_name' => 'FOH X32',
            'configuration' => $this->healthyConfiguration(),
        ]);

        $this->assertSame('X32 Configuration', $workspace['header']['title']);
        $this->assertSame(
            'Learned from FOH X32 · Scene 02 – Band Rehearsal',
            $workspace['header']['learn_context'],
        );
    }

    #[Test]
    public function it_omits_scene_name_from_header_when_not_available(): void
    {
        $workspace = $this->builder->build([
            'device_name' => 'FOH X32',
            'scene_number' => '01',
            'configuration' => $this->healthyConfiguration([
                'identity' => array_merge($this->healthyConfiguration()['identity'], [
                    'scene_number' => ['value' => '01', 'state' => 'learned'],
                    'scene_name' => ['value' => null, 'state' => 'not_learned', 'reason' => 'fixture_transport'],
                ]),
            ]),
        ]);

        $this->assertSame(
            'Learned from FOH X32 · Scene 01',
            $workspace['header']['learn_context'],
        );
    }

    #[Test]
    public function it_uses_scene_unknown_when_scene_number_missing(): void
    {
        $workspace = $this->builder->build([
            'device_name' => 'FOH X32',
            'configuration' => $this->healthyConfiguration([
                'identity' => array_merge($this->healthyConfiguration()['identity'], [
                    'scene_number' => ['value' => null, 'state' => 'not_learned'],
                ]),
            ]),
        ]);

        $this->assertSame(
            'Learned from FOH X32 · Scene unknown',
            $workspace['header']['learn_context'],
        );
    }

    #[Test]
    public function it_marks_status_not_learned_when_configuration_missing(): void
    {
        $workspace = $this->builder->build([
            'device_name' => 'FOH X32',
            'scene_number' => '01',
        ]);

        $this->assertSame('not_learned', $workspace['status']['state']);
        $this->assertSame('Not learned', $workspace['status']['label']);
    }

    #[Test]
    public function it_marks_status_partial_when_audit_required_fields_are_missing(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration([
                'identity' => array_merge($this->healthyConfiguration()['identity'], [
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                ]),
                'globals' => array_merge($this->healthyConfiguration()['globals'], [
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                    'sample_rate' => ['value' => null, 'state' => 'not_learned'],
                    'clock_source' => ['value' => null, 'state' => 'not_learned'],
                ]),
                'fx' => ['learned' => false, 'reason' => 'not_implemented', 'slots' => []],
            ]),
        ]);

        $this->assertSame('partial', $workspace['status']['state']);
        $this->assertSame('Partial', $workspace['status']['label']);
    }

    #[Test]
    public function it_marks_status_needs_attention_when_warnings_exist(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration([
                'warnings' => ['Routing table incomplete.'],
            ]),
        ]);

        $this->assertSame('needs_attention', $workspace['status']['state']);
        $this->assertSame('Needs attention', $workspace['status']['label']);
    }

    #[Test]
    public function it_marks_status_needs_attention_when_configuration_structure_is_corrupt(): void
    {
        $configuration = $this->healthyConfiguration();
        unset($configuration['fx']['learned']);

        $workspace = $this->builder->build([
            'configuration' => $configuration,
        ]);

        $this->assertSame('needs_attention', $workspace['status']['state']);
    }

    #[Test]
    public function it_marks_status_complete_only_when_audit_areas_are_fully_captured(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration(),
        ]);

        $this->assertSame('complete', $workspace['status']['state']);
        $this->assertSame('Complete', $workspace['status']['label']);
    }

    #[Test]
    public function it_does_not_mark_status_complete_when_firmware_is_missing(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration([
                'identity' => array_merge($this->healthyConfiguration()['identity'], [
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                ]),
                'globals' => array_merge($this->healthyConfiguration()['globals'], [
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                ]),
            ]),
        ]);

        $this->assertSame('partial', $workspace['status']['state']);
    }

    #[Test]
    public function it_displays_firmware_when_learned(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration(),
        ]);

        $this->assertSame('4.06', $this->fieldValue($workspace, 'console', 'Firmware'));
    }

    #[Test]
    public function it_displays_firmware_not_captured_yet_when_missing(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration([
                'identity' => array_merge($this->healthyConfiguration()['identity'], [
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                ]),
                'globals' => array_merge($this->healthyConfiguration()['globals'], [
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                ]),
            ]),
        ]);

        $this->assertSame('Not captured yet', $this->fieldValue($workspace, 'console', 'Firmware'));
    }

    #[Test]
    public function it_lists_missing_audit_items_in_learn_status_using_operator_language(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration([
                'identity' => array_merge($this->healthyConfiguration()['identity'], [
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                ]),
                'globals' => array_merge($this->healthyConfiguration()['globals'], [
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                    'sample_rate' => ['value' => null, 'state' => 'not_learned'],
                    'clock_source' => ['value' => null, 'state' => 'not_learned'],
                ]),
                'fx' => ['learned' => false, 'reason' => 'not_implemented', 'slots' => []],
                'dcas' => [[
                    'number' => 1,
                    'membership' => ['value' => null, 'state' => 'not_learned', 'reason' => 'membership_not_derived'],
                ]],
                'matrices' => [[
                    'number' => 1,
                    'sources' => ['value' => null, 'state' => 'not_learned', 'reason' => 'matrix_source_routing_not_in_configuration_scope'],
                ]],
            ]),
        ]);

        $learnStatusValues = collect($workspace['identity']['learn_status']['fields'])
            ->where('label', 'Not yet captured')
            ->pluck('value')
            ->all();

        $this->assertContains('Firmware not captured yet', $learnStatusValues);
        $this->assertContains('Sample rate not captured yet', $learnStatusValues);
        $this->assertContains('Clock source not captured yet', $learnStatusValues);
        $this->assertContains('FX inventory not captured yet', $learnStatusValues);
        $this->assertContains('DCA membership not captured yet', $learnStatusValues);
        $this->assertContains('Matrix sources not captured yet', $learnStatusValues);
        $this->assertStringNotContainsString('not_implemented', implode(' ', $learnStatusValues));
    }

    #[Test]
    public function it_renders_identity_fields_with_not_captured_yet_for_unknowns(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration([
                'identity' => array_merge($this->healthyConfiguration()['identity'], [
                    'scene_name' => ['value' => null, 'state' => 'not_learned'],
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                ]),
                'globals' => array_merge($this->healthyConfiguration()['globals'], [
                    'sample_rate' => ['value' => null, 'state' => 'not_learned'],
                    'clock_source' => ['value' => null, 'state' => 'not_learned'],
                    'firmware' => ['value' => null, 'state' => 'not_learned'],
                ]),
                'fx' => ['learned' => false, 'reason' => 'fixture_transport_not_configuration_learned', 'slots' => []],
            ]),
        ]);

        $this->assertSame('FOH X32', $this->fieldValue($workspace, 'console', 'Console Name'));
        $this->assertSame('foh-x32', $this->fieldValue($workspace, 'console', 'Device Key'));
        $this->assertSame('Not captured yet', $this->fieldValue($workspace, 'console', 'Firmware'));
        $this->assertSame('Not captured yet', $this->fieldValue($workspace, 'scene', 'Scene Name'));
        $this->assertSame('Not captured yet', $this->fieldValue($workspace, 'clock', 'Sample Rate'));
        $this->assertSame('Preview data', $this->fieldValue($workspace, 'learn_status', 'Source'));
    }

    #[Test]
    public function it_builds_status_hint_and_legend(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration([
                'globals' => array_merge($this->healthyConfiguration()['globals'], [
                    'sample_rate' => ['value' => null, 'state' => 'not_learned'],
                ]),
            ]),
        ]);

        $this->assertSame('partial', $workspace['status']['state']);
        $this->assertSame('Some PH043 audit areas are not fully captured yet', $workspace['status']['hint']);
        $this->assertCount(4, $workspace['status_legend']);
        $this->assertTrue(collect($workspace['status_legend'])->firstWhere('state', 'partial')['active']);
    }

    #[Test]
    public function it_formats_sample_rate_and_clock_source_for_operators(): void
    {
        $workspace = $this->builder->build([
            'configuration' => $this->healthyConfiguration(),
        ]);

        $this->assertSame('48 kHz', $this->fieldValue($workspace, 'clock', 'Sample Rate'));
        $this->assertSame('Internal', $this->fieldValue($workspace, 'clock', 'Clock Source'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function healthyConfiguration(array $overrides = []): array
    {
        $base = [
            'source' => 'fake_fixture',
            'learned_at' => '2026-06-17T10:00:00+00:00',
            'warnings' => [],
            'identity' => [
                'console_name' => ['value' => 'FOH X32', 'state' => 'learned'],
                'device_key' => ['value' => 'foh-x32', 'state' => 'learned'],
                'scene_number' => ['value' => '02', 'state' => 'learned'],
                'scene_name' => ['value' => 'Band Rehearsal', 'state' => 'learned'],
                'model' => ['value' => 'X32', 'state' => 'learned'],
                'firmware' => ['value' => '4.06', 'state' => 'learned'],
            ],
            'globals' => [
                'sample_rate' => ['value' => '48K', 'state' => 'learned'],
                'clock_source' => ['value' => 'INT', 'state' => 'learned'],
                'firmware' => ['value' => '4.06', 'state' => 'learned'],
            ],
            'channels' => [[
                'number' => 1,
                'name' => ['value' => 'Kick', 'state' => 'learned'],
            ]],
            'buses' => [[
                'number' => 1,
                'name' => ['value' => 'IEM 1', 'state' => 'learned'],
            ]],
            'dcas' => [[
                'number' => 1,
                'membership' => ['value' => [1], 'state' => 'learned'],
            ]],
            'matrices' => [[
                'number' => 1,
                'sources' => ['value' => [], 'state' => 'learned'],
            ]],
            'fx' => [
                'learned' => true,
                'slots' => [],
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    private function fieldValue(array $workspace, string $cardKey, string $label): string
    {
        foreach ($workspace['identity'][$cardKey]['fields'] as $field) {
            if ($field['label'] === $label) {
                return $field['value'];
            }
        }

        $this->fail(sprintf('Field %s not found on card %s.', $label, $cardKey));
    }
}
