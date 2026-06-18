<?php

namespace App\Models;

use App\Enums\EffectRoutingTargetSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EffectPackageItemTargetSection extends Model
{
    protected $fillable = [
        'effect_package_item_id',
        'target_section',
    ];

    protected function casts(): array
    {
        return [
            'target_section' => EffectRoutingTargetSection::class,
        ];
    }

    public function effectPackageItem(): BelongsTo
    {
        return $this->belongsTo(EffectPackageItem::class);
    }
}
