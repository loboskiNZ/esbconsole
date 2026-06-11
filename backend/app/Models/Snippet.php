<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\SnippetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Snippet extends Model
{
    /** @use HasFactory<SnippetFactory> */
    use HasFactory, HasPublicId;

    public const SOURCE_CHART_CROP = 'chart_crop';

    public const SOURCE_PHOTO = 'photo';

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_CLONE = 'clone';

    public const SOURCE_DRAWING = 'drawing';

    public const FRESHNESS_CURRENT = 'current';

    public const FRESHNESS_OUT_OF_DATE = 'out_of_date';

    public const FRESHNESS_NEEDS_REVIEW = 'needs_review';

    protected $fillable = [
        'song_instrument_part_id',
        'cue_id',
        'source_type',
        'source_snippet_id',
        'source_chart_id',
        'freshness_state',
        'is_active',
        'title',
        'storage_reference',
        'checksum',
        'annotation_storage_reference',
        'markup_storage_reference',
        'rendered_storage_reference',
        'source_metadata',
        'chart_revision_at_creation',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'source_metadata' => 'array',
        ];
    }

    public function songInstrumentPart(): BelongsTo
    {
        return $this->belongsTo(SongInstrumentPart::class);
    }

    public function cue(): BelongsTo
    {
        return $this->belongsTo(Cue::class);
    }

    public function sourceSnippet(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_snippet_id');
    }

    public function sourceChart(): BelongsTo
    {
        return $this->belongsTo(Chart::class, 'source_chart_id');
    }

    public function clonedSnippets(): HasMany
    {
        return $this->hasMany(self::class, 'source_snippet_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
