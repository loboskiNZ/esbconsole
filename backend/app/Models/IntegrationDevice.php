<?php

namespace App\Models;

use Database\Factories\IntegrationDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationDevice extends Model
{
    /** @use HasFactory<IntegrationDeviceFactory> */
    use HasFactory;

    public const TYPE_X32 = 'x32';

    public const TYPE_LIGHTING = 'lighting';

    public const TYPE_ABLETON = 'ableton';

    public const TYPE_MUSICIAN_DEVICE = 'musician_device';

    public const TYPE_VIDEO = 'video';

    public const TYPE_CUSTOM = 'custom';

    public const CONNECTION_STATUS_UNVALIDATED = 'unvalidated';

    public const CONNECTION_STATUS_VALID = 'valid';

    public const CONNECTION_STATUS_INVALID = 'invalid';

    public const CONNECTION_STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'band_id',
        'device_key',
        'name',
        'device_type',
        'enabled',
        'connection_status',
        'configuration',
        'last_validated_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'configuration' => 'array',
            'last_validated_at' => 'datetime',
        ];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function integrationConnectionProfiles(): HasMany
    {
        return $this->hasMany(IntegrationConnectionProfile::class);
    }

    public function performanceDeviceAssignments(): HasMany
    {
        return $this->hasMany(PerformanceDeviceAssignment::class);
    }
}
