<?php

namespace Tests\Feature;

use App\Models\ActionDefinition;
use App\Models\ActionParameter;
use App\Models\ActionType;
use App\Models\Band;
use App\Models\Cue;
use App\Models\CueAction;
use App\Models\Performance;
use App\Models\RuntimeActionItem;
use App\Models\RuntimeActionPlan;
use App\Models\RuntimeAuditRecord;
use App\Models\RuntimeEvent;
use App\Models\RuntimeDispatch;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use App\Services\AdapterKeyResolver;
use App\Services\RuntimeDispatchBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RuntimeDispatchDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_dispatch_can_be_built_from_ready_runtime_action_plan(): void
    {
        $plan = $this->createReadyPlan(withItems: true);
        $builder = app(RuntimeDispatchBuilder::class);

        $result = $builder->build($plan);

        $this->assertTrue($result->created);
        $this->assertSame(RuntimeDispatch::STATUS_READY, $result->runtimeDispatch->status);
        $this->assertDatabaseHas('runtime_dispatches', [
            'id' => $result->runtimeDispatch->id,
            'runtime_action_plan_id' => $plan->id,
            'performance_id' => $plan->performance_id,
            'status' => 'ready',
        ]);
    }

    public function test_runtime_dispatch_items_are_created_from_runtime_action_items(): void
    {
        $plan = $this->createReadyPlan(withItems: true);
        $builder = app(RuntimeDispatchBuilder::class);

        $result = $builder->build($plan);

        $this->assertCount(1, $result->runtimeDispatch->runtimeDispatchItems);
        $actionItem = $plan->runtimeActionItems->first();
        $dispatchItem = $result->runtimeDispatch->runtimeDispatchItems->first();

        $this->assertSame($actionItem->id, $dispatchItem->runtime_action_item_id);
        $this->assertSame($actionItem->action_type_code, $dispatchItem->action_type_code);
        $this->assertSame(['scene' => '05'], $dispatchItem->payload['parameters']);
    }

    public function test_dispatch_item_order_follows_runtime_action_item_sort_order(): void
    {
        $plan = $this->createReadyPlan(withItems: true, itemCount: 2);
        $builder = app(RuntimeDispatchBuilder::class);

        $result = $builder->build($plan);

        $this->assertSame([10, 20], $result->runtimeDispatch->runtimeDispatchItems->pluck('sort_order')->all());
    }

    #[DataProvider('adapterKeyMappingProvider')]
    public function test_adapter_key_is_derived_correctly(string $actionTypeCode, string $expectedAdapterKey): void
    {
        $resolver = app(AdapterKeyResolver::class);

        $this->assertSame($expectedAdapterKey, $resolver->resolve($actionTypeCode));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function adapterKeyMappingProvider(): array
    {
        return [
            'x32 scene' => ['X32_SCENE', AdapterKeyResolver::ADAPTER_X32],
            'x32 snippet' => ['X32_SNIPPET', AdapterKeyResolver::ADAPTER_X32],
            'light mode' => ['LIGHT_MODE', AdapterKeyResolver::ADAPTER_LIGHTING],
            'light scene' => ['LIGHT_SCENE', AdapterKeyResolver::ADAPTER_LIGHTING],
            'musician message' => ['MUSICIAN_MESSAGE', AdapterKeyResolver::ADAPTER_MUSICIAN_DEVICE],
            'musician chart' => ['MUSICIAN_CHART', AdapterKeyResolver::ADAPTER_MUSICIAN_DEVICE],
            'video cue' => ['VIDEO_CUE', AdapterKeyResolver::ADAPTER_VIDEO],
            'custom' => ['CUSTOM', AdapterKeyResolver::ADAPTER_CUSTOM],
            'unknown maps to custom' => ['UNKNOWN_TYPE', AdapterKeyResolver::ADAPTER_CUSTOM],
        ];
    }

    public function test_non_ready_runtime_action_plan_cannot_be_built(): void
    {
        $plan = $this->createReadyPlan(withItems: false);
        $plan->update(['status' => RuntimeActionPlan::STATUS_PENDING]);
        $builder = app(RuntimeDispatchBuilder::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Runtime dispatch can only be built from a ready RuntimeActionPlan.');

        $builder->build($plan->fresh());
    }

    public function test_rebuilding_same_plan_does_not_create_duplicate_runtime_dispatch_rows(): void
    {
        $plan = $this->createReadyPlan(withItems: true);
        $builder = app(RuntimeDispatchBuilder::class);

        $first = $builder->build($plan);
        $second = $builder->build($plan->fresh());

        $this->assertFalse($second->created);
        $this->assertSame($first->runtimeDispatch->id, $second->runtimeDispatch->id);
        $this->assertDatabaseCount('runtime_dispatches', 1);
    }

    public function test_rebuilding_same_plan_does_not_create_duplicate_runtime_dispatch_items(): void
    {
        $plan = $this->createReadyPlan(withItems: true, itemCount: 2);
        $builder = app(RuntimeDispatchBuilder::class);

        $first = $builder->build($plan);
        $second = $builder->build($plan->fresh());

        $this->assertSame(
            $first->runtimeDispatch->runtimeDispatchItems->pluck('id')->all(),
            $second->runtimeDispatch->runtimeDispatchItems->pluck('id')->all(),
        );
        $this->assertDatabaseCount('runtime_dispatch_items', 2);
    }

    public function test_dispatch_audit_records_are_created(): void
    {
        $plan = $this->createReadyPlan(withItems: true);
        $builder = app(RuntimeDispatchBuilder::class);

        $result = $builder->build($plan);

        $stages = RuntimeAuditRecord::query()
            ->where('runtime_action_plan_id', $plan->id)
            ->pluck('stage')
            ->all();

        $this->assertContains(RuntimeAuditRecord::STAGE_DISPATCH_CREATED, $stages);
        $this->assertContains(RuntimeAuditRecord::STAGE_DISPATCH_ITEM_CREATED, $stages);

        $builder->build($plan->fresh());

        $this->assertContains(
            RuntimeAuditRecord::STAGE_DISPATCH_BUILD_SKIPPED,
            RuntimeAuditRecord::query()
                ->where('runtime_action_plan_id', $plan->id)
                ->pluck('stage')
                ->all(),
        );
    }

    public function test_non_ready_plan_creates_dispatch_build_failed_audit_record(): void
    {
        $plan = $this->createReadyPlan(withItems: false);
        $plan->update(['status' => RuntimeActionPlan::STATUS_PENDING]);
        $builder = app(RuntimeDispatchBuilder::class);

        try {
            $builder->build($plan->fresh());
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertDatabaseHas('runtime_audit_records', [
            'runtime_action_plan_id' => $plan->id,
            'stage' => RuntimeAuditRecord::STAGE_DISPATCH_BUILD_FAILED,
        ]);
    }

    public function test_no_execution_adapters_or_hardware_calls_exist(): void
    {
        $this->assertFalse(class_exists(\App\Services\X32\SocketX32Transport::class));
        $this->assertFalse(class_exists(\App\Services\LightingAdapter::class));
        $this->assertFalse(class_exists(\App\Services\ExecutionDispatcher::class));

        $builderMethods = get_class_methods(RuntimeDispatchBuilder::class);
        $this->assertNotContains('dispatch', $builderMethods);
        $this->assertNotContains('execute', $builderMethods);
        $this->assertNotContains('send', $builderMethods);
    }

    private function createReadyPlan(bool $withItems, int $itemCount = 1): RuntimeActionPlan
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

        $event = RuntimeEvent::factory()->forPerformance($performance)->create([
            'runtime_identity' => '001.003',
            'song_code' => '001',
            'cue_number' => '003',
            'status' => RuntimeEvent::STATUS_PLANNED,
        ]);

        $plan = RuntimeActionPlan::factory()->create([
            'runtime_event_id' => $event->id,
            'performance_id' => $performance->id,
            'cue_id' => $cue->id,
            'runtime_identity' => '001.003',
            'status' => RuntimeActionPlan::STATUS_READY,
        ]);

        if ($withItems) {
            $actionTypes = [
                ['X32_SCENE', 'RECALL_INTRO_SCENE', 'Recall Intro Scene', 10, ['scene' => '05']],
                ['LIGHT_SCENE', 'STAGE_WASH', 'Stage Wash Blue', 20, ['mode' => 'blue']],
            ];

            for ($i = 0; $i < $itemCount; $i++) {
                [$typeCode, $definitionCode, $definitionName, $sortOrder, $parameters] = $actionTypes[$i];

                $type = ActionType::query()->where('code', $typeCode)->firstOrFail();
                $definition = ActionDefinition::factory()->forBand($band)->create([
                    'action_type_id' => $type->id,
                    'code' => $definitionCode,
                    'name' => $definitionName,
                ]);

                if ($parameters !== []) {
                    foreach ($parameters as $name => $value) {
                        ActionParameter::factory()->create([
                            'action_definition_id' => $definition->id,
                            'parameter_name' => $name,
                            'parameter_value' => $value,
                        ]);
                    }
                }

                CueAction::factory()->create([
                    'cue_id' => $cue->id,
                    'action_definition_id' => $definition->id,
                    'sort_order' => $sortOrder,
                    'enabled' => true,
                ]);

                RuntimeActionItem::factory()->create([
                    'runtime_action_plan_id' => $plan->id,
                    'action_definition_id' => $definition->id,
                    'action_type_code' => $typeCode,
                    'action_definition_code' => $definitionCode,
                    'action_definition_name' => $definitionName,
                    'sort_order' => $sortOrder,
                    'parameters' => $parameters,
                    'status' => RuntimeActionItem::STATUS_READY,
                ]);
            }
        }

        return $plan->fresh(['runtimeActionItems', 'runtimeEvent']);
    }
}
