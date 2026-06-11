<?php

namespace Tests\Feature;

use App\Models\ActionDefinition;
use App\Models\ActionParameter;
use App\Models\ActionType;
use App\Models\Band;
use App\Models\Cue;
use App\Models\CueAction;
use App\Models\Performance;
use App\Models\RuntimeAuditRecord;
use App\Models\RuntimeEvent;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use App\Services\CueActionResolver;
use App\Services\RuntimeEventPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RuntimeEventDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_event_can_be_created_for_a_performance(): void
    {
        $performance = Performance::factory()->create();

        $event = RuntimeEvent::factory()->forPerformance($performance)->create([
            'source' => 'ABLETON',
            'event_type' => 'CUE_ENTER',
            'runtime_identity' => '001.003',
            'song_code' => '001',
            'cue_number' => '003',
            'status' => RuntimeEvent::STATUS_RECEIVED,
            'received_at' => now(),
            'payload' => ['channel' => 'midi'],
        ]);

        $this->assertTrue($event->performance->is($performance));
        $this->assertTrue($performance->fresh()->runtimeEvents->contains($event));
        $this->assertDatabaseHas('runtime_events', [
            'id' => $event->id,
            'performance_id' => $performance->id,
            'runtime_identity' => '001.003',
            'status' => RuntimeEvent::STATUS_RECEIVED,
        ]);
    }

    public function test_runtime_event_planner_resolves_valid_runtime_identity_to_correct_cue(): void
    {
        $scenario = $this->buildPerformanceScenario();
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');

        $result = app(RuntimeEventPlanner::class)->plan($event);

        $this->assertTrue($result->resolutionSucceeded);
        $this->assertTrue($result->planningSucceeded);
        $this->assertSame(RuntimeEvent::STATUS_PLANNED, $result->runtimeEvent->status);
        $this->assertNotNull($result->runtimeActionPlan);
        $this->assertTrue($result->runtimeActionPlan->cue->is($scenario['cue']));
    }

    public function test_runtime_event_planner_creates_runtime_action_plan(): void
    {
        $scenario = $this->buildPerformanceScenario();
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');

        $result = app(RuntimeEventPlanner::class)->plan($event);

        $this->assertDatabaseHas('runtime_action_plans', [
            'runtime_event_id' => $event->id,
            'performance_id' => $scenario['performance']->id,
            'cue_id' => $scenario['cue']->id,
            'runtime_identity' => '001.003',
            'status' => 'ready',
        ]);
        $this->assertTrue($event->fresh()->runtimeActionPlan->is($result->runtimeActionPlan));
    }

    public function test_runtime_event_planner_creates_ordered_runtime_action_items_from_cue_action_resolver(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true, includeSecondAction: true);
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');

        $result = app(RuntimeEventPlanner::class)->plan($event);

        $items = $result->runtimeActionPlan->runtimeActionItems;
        $this->assertCount(2, $items);
        $this->assertSame(['RECALL_INTRO_SCENE', 'SECOND'], $items->pluck('action_definition_code')->all());
        $this->assertSame([10, 20], $items->pluck('sort_order')->all());
    }

    public function test_runtime_event_planner_stores_action_parameters_as_json_snapshots(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true);
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');

        $result = app(RuntimeEventPlanner::class)->plan($event);

        $item = $result->runtimeActionPlan->runtimeActionItems->first();
        $this->assertSame(['scene' => '05'], $item->parameters);
        $this->assertSame('X32_SCENE', $item->action_type_code);
        $this->assertSame('Recall Intro Scene', $item->action_definition_name);
    }

    public function test_runtime_event_planner_excludes_disabled_cue_actions(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true, includeDisabledCueAction: true);
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');

        $result = app(RuntimeEventPlanner::class)->plan($event);

        $this->assertCount(1, $result->runtimeActionPlan->runtimeActionItems);
        $this->assertSame('RECALL_INTRO_SCENE', $result->runtimeActionPlan->runtimeActionItems->first()->action_definition_code);
    }

    public function test_runtime_event_planner_excludes_disabled_action_definitions(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: false);
        $band = $scenario['band'];
        $cue = $scenario['cue'];
        $type = ActionType::query()->where('code', 'X32_SCENE')->firstOrFail();

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

        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');
        $result = app(RuntimeEventPlanner::class)->plan($event);

        $this->assertCount(1, $result->runtimeActionPlan->runtimeActionItems);
        $this->assertSame('ENABLED_ACTION', $result->runtimeActionPlan->runtimeActionItems->first()->action_definition_code);
    }

    public function test_failed_cue_resolution_marks_runtime_event_as_failed_resolution(): void
    {
        $performance = Performance::factory()->create();
        $event = $this->createRuntimeEvent($performance, '999.999');

        $result = app(RuntimeEventPlanner::class)->plan($event);

        $this->assertFalse($result->resolutionSucceeded);
        $this->assertFalse($result->planningSucceeded);
        $this->assertSame(RuntimeEvent::STATUS_FAILED_RESOLUTION, $result->runtimeEvent->status);
        $this->assertNull($result->runtimeActionPlan);
    }

    public function test_failed_cue_resolution_creates_audit_record_and_no_action_items(): void
    {
        $performance = Performance::factory()->create();
        $event = $this->createRuntimeEvent($performance, '999.999');

        app(RuntimeEventPlanner::class)->plan($event);

        $this->assertDatabaseHas('runtime_audit_records', [
            'runtime_event_id' => $event->id,
            'stage' => RuntimeAuditRecord::STAGE_RESOLUTION_FAILED,
        ]);
        $this->assertDatabaseMissing('runtime_action_plans', [
            'runtime_event_id' => $event->id,
        ]);
        $this->assertDatabaseCount('runtime_action_items', 0);
    }

    public function test_cue_with_no_actions_produces_ready_plan_with_zero_items(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: false);
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');

        $result = app(RuntimeEventPlanner::class)->plan($event);

        $this->assertTrue($result->planningSucceeded);
        $this->assertSame('ready', $result->runtimeActionPlan->status);
        $this->assertCount(0, $result->runtimeActionPlan->runtimeActionItems);
        $this->assertSame(RuntimeEvent::STATUS_PLANNED, $result->runtimeEvent->status);
    }

    public function test_runtime_audit_records_are_created_for_received_resolved_and_planned_lifecycle_stages(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true);
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');

        app(RuntimeEventPlanner::class)->plan($event);

        $stages = RuntimeAuditRecord::query()
            ->where('runtime_event_id', $event->id)
            ->pluck('stage')
            ->all();

        $this->assertContains(RuntimeAuditRecord::STAGE_EVENT_RECEIVED, $stages);
        $this->assertContains(RuntimeAuditRecord::STAGE_CUE_RESOLVED, $stages);
        $this->assertContains(RuntimeAuditRecord::STAGE_ACTIONS_PLANNED, $stages);
        $this->assertContains(RuntimeAuditRecord::STAGE_ACTION_ITEM_CREATED, $stages);
    }

    public function test_no_execution_adapters_or_hardware_calls_exist(): void
    {
        $this->assertFalse(class_exists(\App\Services\X32\SocketX32Transport::class));
        $this->assertFalse(class_exists(\App\Services\LightingAdapter::class));
        $this->assertFalse(class_exists(\App\Services\ExecutionDispatcher::class));

        $plannerMethods = get_class_methods(RuntimeEventPlanner::class);
        $this->assertNotContains('dispatch', $plannerMethods);
        $this->assertNotContains('execute', $plannerMethods);
    }

    public function test_runtime_event_planner_reuses_cue_action_resolver(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true);
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');

        $this->mock(CueActionResolver::class, function (MockInterface $mock) use ($scenario): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->with(Mockery::on(fn ($cue) => $cue->is($scenario['cue'])))
                ->andReturn(app(CueActionResolver::class)->resolve($scenario['cue']));
        });

        $result = app(RuntimeEventPlanner::class)->plan($event);

        $this->assertTrue($result->planningSucceeded);
        $this->assertCount(1, $result->runtimeActionPlan->runtimeActionItems);
    }

    public function test_planning_same_runtime_event_twice_does_not_create_duplicate_plans(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true);
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');
        $planner = app(RuntimeEventPlanner::class);

        $firstResult = $planner->plan($event);
        $secondResult = $planner->plan($event->fresh());

        $this->assertTrue($firstResult->planningSucceeded);
        $this->assertTrue($secondResult->planningSucceeded);
        $this->assertSame(
            $firstResult->runtimeActionPlan->id,
            $secondResult->runtimeActionPlan->id,
        );
        $this->assertDatabaseCount('runtime_action_plans', 1);
        $this->assertDatabaseHas('runtime_action_plans', [
            'runtime_event_id' => $event->id,
        ]);
    }

    public function test_planning_same_runtime_event_twice_does_not_create_duplicate_action_items(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true, includeSecondAction: true);
        $event = $this->createRuntimeEvent($scenario['performance'], '001.003');
        $planner = app(RuntimeEventPlanner::class);

        $firstResult = $planner->plan($event);
        $secondResult = $planner->plan($event->fresh());

        $this->assertCount(2, $firstResult->runtimeActionPlan->runtimeActionItems);
        $this->assertCount(2, $secondResult->runtimeActionPlan->runtimeActionItems);
        $this->assertSame(
            $firstResult->runtimeActionPlan->runtimeActionItems->pluck('id')->all(),
            $secondResult->runtimeActionPlan->runtimeActionItems->pluck('id')->all(),
        );
        $this->assertDatabaseCount('runtime_action_items', 2);
    }

    /**
     * @return array{
     *     band: Band,
     *     performance: Performance,
     *     cue: Cue,
     *     song: Song
     * }
     */
    private function buildPerformanceScenario(
        bool $withActions = false,
        bool $includeSecondAction = false,
        bool $includeDisabledCueAction = false,
    ): array {
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);
        $performance = Performance::factory()->forShow($show)->create();
        $song = Song::factory()->forBand($band)->create(['song_code' => '001']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '003', 'name' => 'Chorus']);

        ShowPlaylistItem::factory()->create([
            'show_id' => $show->id,
            'song_id' => $song->id,
            'position' => 1,
            'ableton_pgm' => 1,
        ]);

        if ($withActions) {
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
                'sort_order' => 10,
                'enabled' => true,
            ]);

            if ($includeSecondAction) {
                $second = ActionDefinition::factory()->forBand($band)->create([
                    'action_type_id' => $type->id,
                    'code' => 'SECOND',
                    'name' => 'Second Action',
                ]);

                CueAction::factory()->create([
                    'cue_id' => $cue->id,
                    'action_definition_id' => $second->id,
                    'sort_order' => 20,
                    'enabled' => true,
                ]);
            }

            if ($includeDisabledCueAction) {
                $disabledDefinition = ActionDefinition::factory()->forBand($band)->create([
                    'action_type_id' => $type->id,
                    'code' => 'DISABLED_CUE_ACTION',
                    'name' => 'Disabled Cue Action',
                ]);

                CueAction::factory()->create([
                    'cue_id' => $cue->id,
                    'action_definition_id' => $disabledDefinition->id,
                    'sort_order' => 30,
                    'enabled' => false,
                ]);
            }
        }

        return [
            'band' => $band,
            'performance' => $performance,
            'cue' => $cue,
            'song' => $song,
        ];
    }

    private function createRuntimeEvent(Performance $performance, string $runtimeIdentity): RuntimeEvent
    {
        [$songCode, $cueNumber] = explode('.', $runtimeIdentity, 2);

        return RuntimeEvent::factory()->forPerformance($performance)->create([
            'runtime_identity' => $runtimeIdentity,
            'song_code' => $songCode,
            'cue_number' => $cueNumber,
            'status' => RuntimeEvent::STATUS_RECEIVED,
            'received_at' => now(),
        ]);
    }
}
