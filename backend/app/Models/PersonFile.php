<?php

namespace App\Models;

use App\Enums\PersonFileType;
use Database\Factories\PersonFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonFile extends Model
{
    /** @use HasFactory<PersonFileFactory> */
    use HasFactory;

    protected $fillable = [
        'person_id',
        'file_type',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'expires_at',
        'notes',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'file_type' => PersonFileType::class,
            'size_bytes' => 'integer',
            'expires_at' => 'datetime',
            'is_public' => 'boolean',
        ];
    }

    protected $attributes = [
        'is_public' => false,
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
