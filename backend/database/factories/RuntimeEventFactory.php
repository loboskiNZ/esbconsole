<?php

namespace Database\Factories;

use App\Models\Performance;
use App\Models\RuntimeEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeEvent>
 */
class RuntimeEventFactory extends Factory
{
    protected $model = RuntimeEvent::class;

    public function definition(): array
    {
        return [
            'performance_id' => Performance::factory(),
            'source' => 'ABLETON',
            'event_type' => 'CUE_ENTER',
            'runtime_identity' => '001.003',
            'song_code' => '001',
            'cue_number' => '003',
            'status' => RuntimeEvent::STATUS_RECEIVED,
            'received_at' => now(),
            'payload' => null,
        ];
    }

    public function forPerformance(Performance $performance): static
    {
        return $this->state(fn () => [
            'performance_id' => $performance->id,
        ]);
    }
}
