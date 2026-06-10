<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'musician_id',
        'device_name',
        'device_type',
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
}
