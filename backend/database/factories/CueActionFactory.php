<?php

namespace Database\Factories;

use App\Models\ActionDefinition;
use App\Models\Band;
use App\Models\Cue;
use App\Models\CueAction;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CueAction>
 */
class CueActionFactory extends Factory
{
    protected $model = CueAction::class;

    public function definition(): array
    {
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['song_code' => '001']);
        $cue = Cue::factory()->create(['song_id' => $song->id]);
        $definition = ActionDefinition::factory()->forBand($band)->create();

        return [
            'cue_id' => $cue->id,
            'action_definition_id' => $definition->id,
            'sort_order' => 0,
            'enabled' => true,
        ];
    }
}
