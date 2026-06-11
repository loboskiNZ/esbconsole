<?php

namespace App\Models;

use Database\Factories\PerformanceDeviceAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceDeviceAssignment extends Model
{
    /** @use HasFactory<PerformanceDeviceAssignmentFactory> */
    use HasFactory;

    public const ROLE_FOH = 'foh';

    public const ROLE_MONITORS = 'monitors';

    public const ROLE_BACKUP = 'backup';

    public const ROLE_CUSTOM = 'custom';

    protected $fillable = [
        'performance_id',
        'integration_device_id',
        'role',
    ];

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function integrationDevice(): BelongsTo
    {
        return $this->belongsTo(IntegrationDevice::class);
    }
}
