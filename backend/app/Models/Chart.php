<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ChartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chart extends Model
{
    /** @use HasFactory<ChartFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'song_id',
        'title',
        'original_filename',
        'storage_reference',
        'checksum',
        'mime_type',
        'file_size',
        'notes',
        'import_batch_id',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function songInstrumentParts(): HasMany
    {
        return $this->hasMany(SongInstrumentPart::class);
    }

    public function sourceSnippets(): HasMany
    {
        return $this->hasMany(Snippet::class, 'source_chart_id');
    }
}
