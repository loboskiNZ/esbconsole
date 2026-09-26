<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOffer extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_EXPIRED = 'expired';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'performance_id',
        'audience_registration_id',
        'ticket_price_tier_id',
        'slug',
        'amount_minor',
        'currency',
        'status',
        'expires_at',
        'first_opened_at',
        'checkout_started_at',
        'consumed_at',
        'abandoned_reminder_sent_at',
        'expiry_reminder_sent_at',
        'source',
        'campaign',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'checkout_started_at' => 'datetime',
            'consumed_at' => 'datetime',
            'abandoned_reminder_sent_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
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
     * @return BelongsTo<TicketPriceTier, $this>
     */
    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(TicketPriceTier::class, 'ticket_price_tier_id');
    }
}
