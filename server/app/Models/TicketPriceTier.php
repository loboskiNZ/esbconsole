<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPriceTier extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'performance_ticketing_configuration_id',
        'name',
        'amount_minor',
        'currency',
        'category',
        'starts_at',
        'ends_at',
        'enabled',
        'private_offer',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'private_offer' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PerformanceTicketingConfiguration, $this>
     */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(PerformanceTicketingConfiguration::class, 'performance_ticketing_configuration_id');
    }

    public function amountDecimal(): string
    {
        return Money::toDecimal((int) $this->amount_minor, (string) $this->currency);
    }
}
