<?php

namespace App\Models;

use App\Enums\EffectPackageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EffectPackageTypeOption extends Model
{
    public const SLUG_PERMANENT = 'permanent';

    public const SLUG_SONG_PACKAGE = 'song-package';

    public const SLUG_SPECIAL_TREATMENT = 'special-treatment';

    protected $table = 'effect_package_types';

    protected $fillable = [
        'name',
        'slug',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function effectPackages(): HasMany
    {
        return $this->hasMany(EffectPackage::class, 'effect_package_type_id');
    }

    public function toPackageTypeEnum(): EffectPackageType
    {
        return match ($this->slug) {
            self::SLUG_PERMANENT => EffectPackageType::Permanent,
            self::SLUG_SONG_PACKAGE => EffectPackageType::SongSelectable,
            self::SLUG_SPECIAL_TREATMENT => EffectPackageType::SpecialTreatment,
            default => EffectPackageType::SongSelectable,
        };
    }
}
