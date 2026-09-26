<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudienceRegistration extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'performance_id',
        'first_name',
        'email',
        'marketing_consent_at',
        'source',
        'campaign',
        'registered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marketing_consent_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }
}
