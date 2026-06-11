<?php

namespace App\Models;

use Database\Factories\CueActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class CueAction extends Model
{
    /** @use HasFactory<CueActionFactory> */
    use HasFactory;

    protected $fillable = [
        'cue_id',
        'action_definition_id',
        'sort_order',
        'enabled',
    ];

    protected static function booted(): void
    {
        static::saving(function (CueAction $cueAction): void {
            $cueAction->assertBandAlignment();
        });
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function cue(): BelongsTo
    {
        return $this->belongsTo(Cue::class);
    }

    public function actionDefinition(): BelongsTo
    {
        return $this->belongsTo(ActionDefinition::class);
    }

    public function assertBandAlignment(): void
    {
        if (! $this->cue_id || ! $this->action_definition_id) {
            return;
        }

        $cueBandId = Cue::query()
            ->whereKey($this->cue_id)
            ->whereHas('song')
            ->with('song:id,band_id')
            ->first()
            ?->song
            ?->band_id;

        $definitionBandId = ActionDefinition::query()
            ->whereKey($this->action_definition_id)
            ->value('band_id');

        if ($cueBandId === null || $definitionBandId === null) {
            return;
        }

        if ($cueBandId !== $definitionBandId) {
            throw new InvalidArgumentException(
                'CueAction action definition must belong to the same band as the cue song.',
            );
        }
    }
}
