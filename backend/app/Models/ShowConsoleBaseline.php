<?php

namespace App\Models;

use App\Enums\ConsoleType;
use App\Models\Concerns\HasPublicId;
use Database\Factories\ShowConsoleBaselineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowConsoleBaseline extends Model
{
    /** @use HasFactory<ShowConsoleBaselineFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'band_id',
        'show_id',
        'source_snapshot_id',
        'baseline_name',
        'console_type',
        'baseline_json',
        'active',
        'saved_at',
    ];

    protected function casts(): array
    {
        return [
            'console_type' => ConsoleType::class,
            'baseline_json' => 'array',
            'active' => 'boolean',
            'saved_at' => 'datetime',
        ];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function sourceSnapshot(): BelongsTo
    {
        return $this->belongsTo(ConsoleLearningSnapshot::class, 'source_snapshot_id');
    }
}
