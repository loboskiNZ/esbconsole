<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketOrder extends Model
{
    public const CHANNEL_PUBLIC = 'public';

    public const CHANNEL_PRIVATE_OFFER = 'private_offer';

    public const CHANNEL_WALK_IN = 'walk_in';

    public const CHANNEL_MANUAL = 'manual';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_NOT_APPLICABLE = 'not_applicable';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_REFUNDED = 'refunded';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'performance_id',
        'purchase_offer_id',
        'audience_registration_id',
        'channel',
        'status',
        'payment_status',
        'amount_minor',
        'currency',
        'quantity',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
        'buyer_name',
        'buyer_email',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
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
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
