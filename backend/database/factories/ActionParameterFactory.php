<?php

namespace Database\Factories;

use App\Models\ActionDefinition;
use App\Models\ActionParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionParameter>
 */
class ActionParameterFactory extends Factory
{
    protected $model = ActionParameter::class;

    public function definition(): array
    {
        return [
            'action_definition_id' => ActionDefinition::factory(),
            'parameter_name' => fake()->unique()->word(),
            'parameter_value' => fake()->word(),
        ];
    }
}
