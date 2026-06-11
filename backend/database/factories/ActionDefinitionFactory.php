<?php

namespace Database\Factories;

use App\Models\ActionDefinition;
use App\Models\ActionType;
use App\Models\Band;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionDefinition>
 */
class ActionDefinitionFactory extends Factory
{
    protected $model = ActionDefinition::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'action_type_id' => ActionType::factory(),
            'code' => 'TEST_ACTION_'.fake()->unique()->lexify('????'),
            'name' => 'Test Action '.fake()->word(),
            'description' => null,
            'enabled' => true,
        ];
    }

    public function forBand(Band $band): static
    {
        return $this->state(fn () => ['band_id' => $band->id]);
    }
}
