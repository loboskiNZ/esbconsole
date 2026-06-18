<?php

namespace App\Models;

use App\Enums\EffectActiveSongSafety;
use App\Enums\EffectImplementationType;
use App\Enums\EffectTempoBehavior;
use App\Enums\X32SlotGroup;
use App\Models\Concerns\HasPublicId;
use Database\Factories\EffectDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EffectDefinition extends Model
{
    /** @use HasFactory<EffectDefinitionFactory> */
    use HasFactory, HasPublicId;

    public const CATEGORY_REVERB = 'reverb';

    public const CATEGORY_PLATE = 'plate';

    public const CATEGORY_DELAY = 'delay';

    public const CATEGORY_DUB_DELAY = 'dub_delay';

    public const CATEGORY_CHORUS = 'chorus';

    public const CATEGORY_GRAPHIC_EQ = 'graphic_eq';

    public const CATEGORY_LIMITER = 'limiter';

    public const CATEGORY_COMPRESSOR = 'compressor';

    public const CATEGORY_ENHANCER = 'enhancer';

    public const CATEGORY_SPECIAL_FX = 'special_fx';

    public const TARGET_VOCAL = 'vocal';

    public const TARGET_HORN = 'horn';

    public const TARGET_FOH = 'foh';

    public const TARGET_DRUM = 'drum';

    public const TARGET_SPECIAL = 'special';

    public const ROLE_AMBIENCE = 'ambience';

    public const ROLE_DELAY = 'delay';

    public const ROLE_PROCESSOR = 'processor';

    public const ROLE_SPECIAL_TREATMENT = 'special_treatment';

    protected $fillable = [
        'name',
        'slug',
        'category',
        'target_section',
        'x32_algorithm_code',
        'x32_algorithm_id',
        'x32_slot_group',
        'effect_role',
        'implementation_type',
        'tempo_behavior',
        'active_song_safety',
        'default_parameters_json',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'x32_slot_group' => X32SlotGroup::class,
            'implementation_type' => EffectImplementationType::class,
            'tempo_behavior' => EffectTempoBehavior::class,
            'active_song_safety' => EffectActiveSongSafety::class,
            'default_parameters_json' => 'array',
            'is_active' => 'boolean',
            'x32_algorithm_id' => 'integer',
        ];
    }

    public function effectPackageItems(): HasMany
    {
        return $this->hasMany(EffectPackageItem::class);
    }

    public function effectPackages(): BelongsToMany
    {
        return $this->belongsToMany(EffectPackage::class, 'effect_package_items')
            ->withPivot([
                'is_required',
                'preferred_slot_number',
                'slot_group_preference',
                'priority',
                'parameter_overrides_json',
                'timing_rules_json',
                'notes',
            ])
            ->withTimestamps()
            ->orderBy('effect_package_items.priority');
    }
}
