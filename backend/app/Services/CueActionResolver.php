<?php

namespace App\Services;

use App\Models\Cue;
use App\Models\CueAction;

class CueActionResolver
{
    public function resolve(Cue $cue): ResolvedCueActionResult
    {
        $cue->loadMissing('song');

        $songCode = $cue->song->song_code;
        $cueNumber = $cue->cue_number;
        $runtimeIdentity = $songCode.'.'.$cueNumber;

        $cueActions = CueAction::query()
            ->where('cue_id', $cue->id)
            ->where('enabled', true)
            ->whereHas('actionDefinition', fn ($query) => $query->where('enabled', true))
            ->with([
                'actionDefinition.actionType',
                'actionDefinition.actionParameters',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $actions = $cueActions->map(function (CueAction $cueAction) {
            $definition = $cueAction->actionDefinition;
            $type = $definition->actionType;

            $parameters = $definition->actionParameters
                ->pluck('parameter_value', 'parameter_name')
                ->all();

            return [
                'cue_action_id' => $cueAction->id,
                'sort_order' => $cueAction->sort_order,
                'action_definition_code' => $definition->code,
                'action_definition_name' => $definition->name,
                'action_type_code' => $type->code,
                'action_type_name' => $type->name,
                'parameters' => $parameters,
            ];
        })->values()->all();

        return new ResolvedCueActionResult(
            cueId: $cue->id,
            songCode: $songCode,
            cueNumber: $cueNumber,
            runtimeIdentity: $runtimeIdentity,
            actions: $actions,
        );
    }
}
