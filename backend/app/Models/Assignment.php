<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'musician_id',
        'instrument_part_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function musician(): BelongsTo
    {
        return $this->belongsTo(Musician::class);
    }

    public function instrumentPart(): BelongsTo
    {
        return $this->belongsTo(InstrumentPart::class);
    }
}
