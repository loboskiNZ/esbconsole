<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentPart extends Model
{
    protected $table = 'instrument_parts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'band_id',
        'name',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('portal.library_connection');
    }

    /**
     * @return HasMany<SongInstrumentPart, $this>
     */
    public function songInstrumentParts(): HasMany
    {
        return $this->hasMany(SongInstrumentPart::class);
    }
}
