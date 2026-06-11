<?php

namespace App\Models;

use Database\Factories\IntegrationConnectionProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConnectionProfile extends Model
{
    /** @use HasFactory<IntegrationConnectionProfileFactory> */
    use HasFactory;

    public const PROTOCOL_OSC = 'osc';

    public const PROTOCOL_MIDI = 'midi';

    public const PROTOCOL_TCP = 'tcp';

    public const PROTOCOL_UDP = 'udp';

    public const PROTOCOL_HTTP = 'http';

    public const PROTOCOL_LOCAL = 'local';

    public const PROTOCOL_CUSTOM = 'custom';

    protected $fillable = [
        'integration_device_id',
        'profile_name',
        'protocol',
        'host',
        'port',
        'path',
        'options',
        'enabled',
        'last_validated_at',
        'last_validation_message',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'enabled' => 'boolean',
            'last_validated_at' => 'datetime',
        ];
    }

    public function integrationDevice(): BelongsTo
    {
        return $this->belongsTo(IntegrationDevice::class);
    }
}
