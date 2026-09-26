<?php

namespace App\Models;

use App\Support\PerformanceEventTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PerformanceTicketingConfiguration extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'performance_id',
        'enabled',
        'capacity',
        'currency',
        'timezone',
        'sales_open_at',
        'sales_close_at',
        'public_sales_enabled',
        'walk_in_sales_enabled',
        'interest_registration_enabled',
        'private_offers_enabled',
        'marketing_registration_enabled',
        'offer_validity_minutes',
        'abandoned_checkout_reminder_minutes',
        'complimentary_allocation',
        'promotional_allocation',
        'default_offer_price_tier_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'public_sales_enabled' => 'boolean',
            'walk_in_sales_enabled' => 'boolean',
            'interest_registration_enabled' => 'boolean',
            'private_offers_enabled' => 'boolean',
            'marketing_registration_enabled' => 'boolean',
            'sales_open_at' => 'datetime',
            'sales_close_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    /**
     * @return HasMany<TicketPriceTier, $this>
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(TicketPriceTier::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<TicketPriceTier, $this>
     */
    public function defaultOfferPriceTier(): BelongsTo
    {
        return $this->belongsTo(TicketPriceTier::class, 'default_offer_price_tier_id');
    }

    public function localInput(?Carbon $value): string
    {
        return PerformanceEventTime::format($value, $this->timezone, 'Y-m-d\TH:i');
    }

    public function formatInstant(?Carbon $value): string
    {
        return PerformanceEventTime::format($value, $this->timezone);
    }
}
