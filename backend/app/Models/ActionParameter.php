<?php

namespace App\Models;

use Database\Factories\ActionParameterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionParameter extends Model
{
    /** @use HasFactory<ActionParameterFactory> */
    use HasFactory;

    protected $fillable = [
        'action_definition_id',
        'parameter_name',
        'parameter_value',
    ];

    public function actionDefinition(): BelongsTo
    {
        return $this->belongsTo(ActionDefinition::class);
    }
}
