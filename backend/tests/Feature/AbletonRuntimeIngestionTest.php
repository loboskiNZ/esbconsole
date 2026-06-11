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
use App\Services\AbletonRuntimeIngestionService;
use App\Services\CueActionResolver;
use App\Services\RuntimeEventPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AbletonRuntimeIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ableton_runtime_ingestion_service_creates_runtime_event_from_valid_runtime_identity(): void
    {
        $scenario = $this->buildPerformanceScenario();
        $service = app(AbletonRuntimeIngestionService::class);

        $result = $service->ingest($scenario['performance']->id, '001.003');

        $this->assertDatabaseHas('runtime_events', [
            'id' => $result->runtimeEvent->id,
            'performance_id' => $scenario['performance']->id,
            'runtime_identity' => '001.003',
        ]);
        $this->assertNotNull($result->runtimeEvent->received_at);
    }

    public function test_ableton_runtime_ingestion_service_derives_song_code_and_cue_number_correctly(): void
    {
        $scenario = $this->buildPerformanceScenario();
        $service = app(AbletonRuntimeIngestionService::class);

        $result = $service->ingest($scenario['performance']->id, '042.007');

        $this->assertSame('042', $result->runtimeEvent->song_code);
        $this->assertSame('007', $result->runtimeEvent->cue_number);
        $this->assertSame('042.007', $result->runtimeEvent->runtime_identity);
    }

    public function test_ableton_runtime_ingestion_service_calls_planner_and_creates_runtime_action_plan(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true);
        $service = app(AbletonRuntimeIngestionService::class);

        $result = $service->ingest($scenario['performance']->id, '001.003');

        $this->assertTrue($result->planResult->planningSucceeded);
        $this->assertNotNull($result->planResult->runtimeActionPlan);
        $this->assertSame(RuntimeEvent::STATUS_PLANNED, $result->runtimeEvent->status);
        $this->assertDatabaseHas('runtime_action_plans', [
            'runtime_event_id' => $result->runtimeEvent->id,
            'cue_id' => $scenario['cue']->id,
            'status' => 'ready',
        ]);
    }

    #[DataProvider('invalidRuntimeIdentitiesProvider')]
    public function test_ableton_runtime_ingestion_service_rejects_invalid_runtime_identity_values(string $runtimeIdentity): void
    {
        $performance = Performance::factory()->create();
        $service = app(AbletonRuntimeIngestionService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Runtime identity must match NNN.NNN format');

        $service->ingest($performance->id, $runtimeIdentity);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRuntimeIdentitiesProvider(): array
    {
        return [
            'single digit parts' => ['1.3'],
            'song code only' => ['001'],
            'short cue number' => ['001.3'],
            'non numeric' => ['abc.def'],
            'song code too long' => ['1000.001'],
        ];
    }

    public function test_ableton_runtime_ingestion_service_preserves_payload_json(): void
    {
        $scenario = $this->buildPerformanceScenario();
        $service = app(AbletonRuntimeIngestionService::class);
        $payload = ['channel' => 'midi', 'note' => 36];

        $result = $service->ingest(
            performanceId: $scenario['performance']->id,
            runtimeIdentity: '001.003',
            payload: $payload,
        );

        $this->assertSame($payload, $result->runtimeEvent->payload);
        $this->assertDatabaseHas('runtime_events', [
            'id' => $result->runtimeEvent->id,
        ]);
    }

    public function test_ableton_runtime_ingestion_service_records_source_as_ableton_by_default(): void
    {
        $scenario = $this->buildPerformanceScenario();
        $service = app(AbletonRuntimeIngestionService::class);

        $result = $service->ingest($scenario['performance']->id, '001.003');

        $this->assertSame(AbletonRuntimeIngestionService::SOURCE_ABLETON, $result->runtimeEvent->source);
    }

    public function test_ableton_runtime_ingestion_service_records_event_type_as_cue_enter_by_default(): void
    {
        $scenario = $this->buildPerformanceScenario();
        $service = app(AbletonRuntimeIngestionService::class);

        $result = $service->ingest($scenario['performance']->id, '001.003');

        $this->assertSame(AbletonRuntimeIngestionService::EVENT_TYPE_CUE_ENTER, $result->runtimeEvent->event_type);
    }

    public function test_failed_cue_resolution_still_creates_runtime_event_and_marks_failed_resolution(): void
    {
        $performance = Performance::factory()->create();
        $service = app(AbletonRuntimeIngestionService::class);

        $result = $service->ingest($performance->id, '999.999');

        $this->assertDatabaseHas('runtime_events', [
            'id' => $result->runtimeEvent->id,
            'runtime_identity' => '999.999',
            'status' => RuntimeEvent::STATUS_FAILED_RESOLUTION,
        ]);
        $this->assertFalse($result->planResult->resolutionSucceeded);
        $this->assertNull($result->planResult->runtimeActionPlan);
        $this->assertDatabaseHas('runtime_audit_records', [
            'runtime_event_id' => $result->runtimeEvent->id,
            'stage' => RuntimeAuditRecord::STAGE_RESOLUTION_FAILED,
        ]);
    }

    public function test_multiple_identical_runtime_identities_create_separate_runtime_event_rows(): void
    {
        $scenario = $this->buildPerformanceScenario();
        $service = app(AbletonRuntimeIngestionService::class);

        $first = $service->ingest($scenario['performance']->id, '001.003');
        $second = $service->ingest($scenario['performance']->id, '001.003');

        $this->assertNotSame($first->runtimeEvent->id, $second->runtimeEvent->id);
        $this->assertDatabaseCount('runtime_events', 2);
        $this->assertDatabaseCount('runtime_action_plans', 2);
    }

    public function test_no_execution_adapters_or_hardware_calls_exist(): void
    {
        $this->assertFalse(class_exists(\App\Services\X32Adapter::class));
        $this->assertFalse(class_exists(\App\Services\LightingAdapter::class));
        $this->assertFalse(class_exists(\App\Services\ExecutionDispatcher::class));

        $serviceMethods = get_class_methods(AbletonRuntimeIngestionService::class);
        $this->assertNotContains('dispatch', $serviceMethods);
        $this->assertNotContains('execute', $serviceMethods);
    }

    public function test_ableton_runtime_ingestion_service_reuses_runtime_event_planner(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true);

        $realPlanner = new RuntimeEventPlanner(app(CueActionResolver::class));

        $this->mock(RuntimeEventPlanner::class, function (MockInterface $mock) use ($realPlanner): void {
            $mock->shouldReceive('plan')
                ->once()
                ->with(Mockery::type(RuntimeEvent::class))
                ->andReturnUsing(fn (RuntimeEvent $event) => $realPlanner->plan($event));
        });

        $result = app(AbletonRuntimeIngestionService::class)->ingest(
            $scenario['performance']->id,
            '001.003',
        );

        $this->assertTrue($result->planResult->planningSucceeded);
    }

    public function test_ableton_runtime_ingestion_delegates_audit_chain_to_runtime_event_planner(): void
    {
        $scenario = $this->buildPerformanceScenario(withActions: true);
        $service = app(AbletonRuntimeIngestionService::class);

        $result = $service->ingest($scenario['performance']->id, '001.003');

        $stages = RuntimeAuditRecord::query()
            ->where('runtime_event_id', $result->runtimeEvent->id)
            ->pluck('stage')
            ->all();

        $this->assertContains(RuntimeAuditRecord::STAGE_EVENT_RECEIVED, $stages);
        $this->assertContains(RuntimeAuditRecord::STAGE_CUE_RESOLVED, $stages);
        $this->assertContains(RuntimeAuditRecord::STAGE_ACTIONS_PLANNED, $stages);
    }

    /**
     * @return array{
     *     band: Band,
     *     performance: Performance,
     *     cue: Cue,
     *     song: Song
     * }
     */
    private function buildPerformanceScenario(bool $withActions = false): array
    {
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
        }

        return [
            'band' => $band,
            'performance' => $performance,
            'cue' => $cue,
            'song' => $song,
        ];
    }
}
