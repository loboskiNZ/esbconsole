<?php

namespace App\Models;

use Database\Factories\ActionDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActionDefinition extends Model
{
    /** @use HasFactory<ActionDefinitionFactory> */
    use HasFactory;

    protected $fillable = [
        'band_id',
        'action_type_id',
        'code',
        'name',
        'description',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function actionType(): BelongsTo
    {
        return $this->belongsTo(ActionType::class);
    }

    public function actionParameters(): HasMany
    {
        return $this->hasMany(ActionParameter::class);
    }

    public function cueActions(): HasMany
    {
        return $this->hasMany(CueAction::class);
    }

    public function runtimeActionItems(): HasMany
    {
        return $this->hasMany(RuntimeActionItem::class);
    }
}
