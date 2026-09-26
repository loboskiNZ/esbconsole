<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    public const STATUS_VALID = 'valid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'ticket_order_id',
        'performance_id',
        'ticket_price_tier_id',
        'amount_minor',
        'currency',
        'status',
        'attendee_name',
        'attendee_email',
    ];

    /**
     * @return BelongsTo<TicketOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'ticket_order_id');
    }

    /**
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    /**
     * @return HasOne<CheckIn, $this>
     */
    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class);
    }
}
