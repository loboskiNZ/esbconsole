<?php

namespace App\Models;

use App\Enums\EffectPackageType;
use App\Models\Concerns\HasPublicId;
use Database\Factories\EffectPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EffectPackage extends Model
{
    /** @use HasFactory<EffectPackageFactory> */
    use HasFactory, HasPublicId;

    public const TARGET_VOCAL = 'vocal';

    public const TARGET_HORN = 'horn';

    public const TARGET_FOH = 'foh';

    public const TARGET_SPECIAL = 'special';

    protected $fillable = [
        'name',
        'slug',
        'effect_package_type_id',
        'package_type',
        'target_section',
        'priority',
        'description',
        'is_default',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'package_type' => EffectPackageType::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'effect_package_type_id' => 'integer',
        ];
    }

    public function effectPackageTypeOption(): BelongsTo
    {
        return $this->belongsTo(EffectPackageTypeOption::class, 'effect_package_type_id');
    }

    public function effectPackageItems(): HasMany
    {
        return $this->hasMany(EffectPackageItem::class)->orderBy('priority');
    }

    public function effectDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(EffectDefinition::class, 'effect_package_items')
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

    public function songEffectAssignments(): HasMany
    {
        return $this->hasMany(SongEffectAssignment::class);
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'song_effect_assignments')
            ->withPivot([
                'priority',
                'assignment_type',
                'enabled',
                'fallback_console_recall_name',
                'fallback_console_recall_type',
                'notes',
            ])
            ->withTimestamps()
            ->orderBy('song_effect_assignments.priority');
    }
}
