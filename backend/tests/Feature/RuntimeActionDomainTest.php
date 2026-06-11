<?php

namespace Tests\Feature;

use App\Models\ActionDefinition;
use App\Models\ActionParameter;
use App\Models\ActionType;
use App\Models\Band;
use App\Models\Cue;
use App\Models\CueAction;
use App\Models\Song;
use App\Services\CueActionResolver;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RuntimeActionDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_types_can_be_created(): void
    {
        $type = ActionType::factory()->create([
            'code' => 'TEST_CUSTOM_TYPE',
            'name' => 'Test Custom Type',
        ]);

        $this->assertDatabaseHas('action_types', [
            'id' => $type->id,
            'code' => 'TEST_CUSTOM_TYPE',
        ]);
    }

    public function test_action_definitions_are_band_scoped_and_code_unique_per_band(): void
    {
        $band = Band::factory()->create();
        $type = ActionType::factory()->create();

        ActionDefinition::factory()->forBand($band)->create([
            'action_type_id' => $type->id,
            'code' => 'RECALL_INTRO',
            'name' => 'Recall Intro Scene',
        ]);

        $otherBand = Band::factory()->create();
        ActionDefinition::factory()->forBand($otherBand)->create([
            'action_type_id' => $type->id,
            'code' => 'RECALL_INTRO',
            'name' => 'Other Band Intro',
        ]);

        $this->expectException(QueryException::class);
        ActionDefinition::factory()->forBand($band)->create([
            'action_type_id' => $type->id,
            'code' => 'RECALL_INTRO',
            'name' => 'Duplicate Code',
        ]);
    }

    public function test_action_parameters_are_unique_per_action_definition(): void
    {
        $definition = ActionDefinition::factory()->create();

        ActionParameter::factory()->create([
            'action_definition_id' => $definition->id,
            'parameter_name' => 'scene',
            'parameter_value' => '05',
        ]);

        $this->expectException(QueryException::class);
        ActionParameter::factory()->create([
            'action_definition_id' => $definition->id,
            'parameter_name' => 'scene',
            'parameter_value' => '06',
        ]);
    }

    public function test_cue_actions_attach_action_definitions_to_cues(): void
    {
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['song_code' => '001']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '003', 'name' => 'Chorus']);
        $definition = ActionDefinition::factory()->forBand($band)->create();

        $cueAction = CueAction::factory()->create([
            'cue_id' => $cue->id,
            'action_definition_id' => $definition->id,
            'sort_order' => 1,
        ]);

        $this->assertTrue($cueAction->cue->is($cue));
        $this->assertTrue($cueAction->actionDefinition->is($definition));
        $this->assertTrue($cue->fresh()->cueActions->contains($cueAction));
    }

    public function test_cross_band_cue_action_attachment_is_rejected(): void
    {
        $bandA = Band::factory()->create();
        $bandB = Band::factory()->create();
        $song = Song::factory()->forBand($bandA)->create(['song_code' => '001']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '003', 'name' => 'Chorus']);
        $definition = ActionDefinition::factory()->forBand($bandB)->create([
            'code' => 'OTHER_BAND_ACTION',
            'name' => 'Other Band Action',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CueAction action definition must belong to the same band as the cue song.');

        CueAction::create([
            'cue_id' => $cue->id,
            'action_definition_id' => $definition->id,
            'sort_order' => 1,
            'enabled' => true,
        ]);
    }

    public function test_disabled_cue_actions_are_excluded_from_resolved_output(): void
    {
        $result = $this->buildResolvedScenario(includeDisabledCueAction: true);

        $this->assertCount(1, $result->actions);
        $this->assertSame('Recall Intro Scene', $result->actions[0]['action_definition_name']);
    }

    public function test_disabled_action_definitions_are_excluded_from_resolved_output(): void
    {
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['song_code' => '001']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '003', 'name' => 'Chorus']);
        $type = ActionType::query()->where('code', 'X32_SCENE')->first();

        $enabled = ActionDefinition::factory()->forBand($band)->create([
            'action_type_id' => $type->id,
            'code' => 'ENABLED_ACTION',
            'name' => 'Enabled Action',
            'enabled' => true,
        ]);

        $disabled = ActionDefinition::factory()->forBand($band)->create([
            'action_type_id' => $type->id,
            'code' => 'DISABLED_ACTION',
            'name' => 'Disabled Action',
            'enabled' => false,
        ]);

        CueAction::factory()->create([
            'cue_id' => $cue->id,
            'action_definition_id' => $enabled->id,
            'sort_order' => 1,
        ]);

        CueAction::factory()->create([
            'cue_id' => $cue->id,
            'action_definition_id' => $disabled->id,
            'sort_order' => 2,
        ]);

        $result = app(CueActionResolver::class)->resolve($cue);

        $this->assertCount(1, $result->actions);
        $this->assertSame('ENABLED_ACTION', $result->actions[0]['action_definition_code']);
    }

    public function test_resolved_actions_are_ordered_by_sort_order(): void
    {
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['song_code' => '001']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '003', 'name' => 'Chorus']);
        $type = ActionType::factory()->create();

        $second = ActionDefinition::factory()->forBand($band)->create([
            'action_type_id' => $type->id,
            'code' => 'SECOND',
            'name' => 'Second Action',
        ]);

        $first = ActionDefinition::factory()->forBand($band)->create([
            'action_type_id' => $type->id,
            'code' => 'FIRST',
            'name' => 'First Action',
        ]);

        CueAction::factory()->create([
            'cue_id' => $cue->id,
            'action_definition_id' => $second->id,
            'sort_order' => 20,
        ]);

        CueAction::factory()->create([
            'cue_id' => $cue->id,
            'action_definition_id' => $first->id,
            'sort_order' => 10,
        ]);

        $result = app(CueActionResolver::class)->resolve($cue);

        $this->assertSame(['FIRST', 'SECOND'], array_column($result->actions, 'action_definition_code'));
    }

    public function test_runtime_identity_is_derived_from_song_code_and_cue_number(): void
    {
        $result = $this->buildResolvedScenario();

        $this->assertSame('001', $result->songCode);
        $this->assertSame('003', $result->cueNumber);
        $this->assertSame('001.003', $result->runtimeIdentity);
    }

    public function test_operator_workflow_resolves_x32_scene_with_parameter_without_execution(): void
    {
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['song_code' => '001']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '003', 'name' => 'Chorus']);

        $type = ActionType::query()->where('code', 'X32_SCENE')->firstOrFail();

        $definition = ActionDefinition::factory()->forBand($band)->create([
            'action_type_id' => $type->id,
            'code' => 'RECALL_INTRO_SCENE',
            'name' => 'Recall Intro Scene',
        ]);

        ActionParameter::factory()->create([
            'action_definition_id' => $definition->id,
            'parameter_name' => 'scene',
            'parameter_value' => '05',
        ]);

        CueAction::factory()->create([
            'cue_id' => $cue->id,
            'action_definition_id' => $definition->id,
            'sort_order' => 1,
        ]);

        $result = app(CueActionResolver::class)->resolve($cue);

        $this->assertSame('001.003', $result->runtimeIdentity);
        $this->assertCount(1, $result->actions);

        $action = $result->actions[0];
        $this->assertSame('X32_SCENE', $action['action_type_code']);
        $this->assertSame('Recall Intro Scene', $action['action_definition_name']);
        $this->assertSame(['scene' => '05'], $action['parameters']);
    }

    private function buildResolvedScenario(bool $includeDisabledCueAction = false)
    {
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['song_code' => '001']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '003', 'name' => 'Chorus']);
        $type = ActionType::query()->where('code', 'X32_SCENE')->firstOrFail();

        $definition = ActionDefinition::factory()->forBand($band)->create([
            'action_type_id' => $type->id,
            'code' => 'RECALL_INTRO_SCENE',
            'name' => 'Recall Intro Scene',
        ]);

        ActionParameter::factory()->create([
            'action_definition_id' => $definition->id,
            'parameter_name' => 'scene',
            'parameter_value' => '05',
        ]);

        CueAction::factory()->create([
            'cue_id' => $cue->id,
            'action_definition_id' => $definition->id,
            'sort_order' => 1,
            'enabled' => true,
        ]);

        if ($includeDisabledCueAction) {
            $disabledDefinition = ActionDefinition::factory()->forBand($band)->create([
                'action_type_id' => $type->id,
                'code' => 'DISABLED_CUE_ACTION',
                'name' => 'Disabled Cue Action',
            ]);

            CueAction::factory()->create([
                'cue_id' => $cue->id,
                'action_definition_id' => $disabledDefinition->id,
                'sort_order' => 2,
                'enabled' => false,
            ]);
        }

        return app(CueActionResolver::class)->resolve($cue);
    }
}
