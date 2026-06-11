<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportEntityMapping extends Model
{
    protected $fillable = [
        'import_batch_id',
        'entity_type',
        'legacy_key',
        'entity_id',
        'public_id',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
