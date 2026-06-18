<?php

namespace Tests\Unit;

use App\Enums\EffectReturnDestination;
use App\Enums\EffectRoutingMode;
use App\Enums\EffectRoutingTargetSection;
use App\Enums\X32SlotGroup;
use App\Models\X32Effect;
use App\Services\Effects\EffectRoutingPlanSuggester;
use Database\Seeders\EffectsAlgorithmReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EffectRoutingPlanSuggesterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EffectsAlgorithmReferenceSeeder::class);
    }

    public function test_graphic_eq_suggests_main_processing_for_foh(): void
    {
        $effect = X32Effect::query()
            ->where('effect_code', 'GEQ')
            ->where('x32_slot_group', X32SlotGroup::Fx5To8)
            ->firstOrFail();

        $suggestion = app(EffectRoutingPlanSuggester::class)->suggest($effect);

        $this->assertSame(EffectRoutingMode::MainProcessing, $suggestion['routing_mode']);
        $this->assertSame([EffectRoutingTargetSection::Foh], $suggestion['target_sections']);
        $this->assertSame(EffectReturnDestination::MainLr, $suggestion['return_destination']);
    }

    public function test_precision_limiter_suggests_main_processing_for_foh(): void
    {
        $effect = X32Effect::query()
            ->where('effect_code', 'LIM')
            ->where('x32_slot_group', X32SlotGroup::Fx5To8)
            ->firstOrFail();

        $suggestion = app(EffectRoutingPlanSuggester::class)->suggest($effect);

        $this->assertSame(EffectRoutingMode::MainProcessing, $suggestion['routing_mode']);
        $this->assertSame([EffectRoutingTargetSection::Foh], $suggestion['target_sections']);
        $this->assertSame(EffectReturnDestination::MainLr, $suggestion['return_destination']);
    }

    public function test_reverb_effect_suggests_send_return_without_forced_target_sections(): void
    {
        $effect = X32Effect::query()
            ->where('effect_code', 'PLAT')
            ->where('x32_slot_group', X32SlotGroup::Fx1To4)
            ->firstOrFail();

        $suggestion = app(EffectRoutingPlanSuggester::class)->suggest($effect);

        $this->assertSame(EffectRoutingMode::SendReturn, $suggestion['routing_mode']);
        $this->assertSame([], $suggestion['target_sections']);
        $this->assertSame(EffectReturnDestination::MainLr, $suggestion['return_destination']);
        $this->assertSame('-10.00', $suggestion['default_return_level']);
    }

    public function test_delay_effect_suggests_send_return_without_forced_target_sections(): void
    {
        $effect = X32Effect::query()
            ->where('effect_code', 'DLY')
            ->where('x32_slot_group', X32SlotGroup::Fx1To4)
            ->firstOrFail();

        $suggestion = app(EffectRoutingPlanSuggester::class)->suggest($effect);

        $this->assertSame(EffectRoutingMode::SendReturn, $suggestion['routing_mode']);
        $this->assertSame([], $suggestion['target_sections']);
        $this->assertSame(EffectReturnDestination::MainLr, $suggestion['return_destination']);
    }
}
