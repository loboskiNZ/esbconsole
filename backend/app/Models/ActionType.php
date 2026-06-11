<?php

namespace App\Models;

use Database\Factories\ActionTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActionType extends Model
{
    /** @use HasFactory<ActionTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function actionDefinitions(): HasMany
    {
        return $this->hasMany(ActionDefinition::class);
    }
}
