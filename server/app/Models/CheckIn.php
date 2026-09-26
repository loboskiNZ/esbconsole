<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'performance_id',
        'ticket_id',
        'guest_list_entry_id',
        'promotional_allocation_id',
        'quantity',
        'checked_in_at',
        'checked_in_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<GuestListEntry, $this>
     */
    public function guestListEntry(): BelongsTo
    {
        return $this->belongsTo(GuestListEntry::class);
    }

    /**
     * @return BelongsTo<PromotionalAllocation, $this>
     */
    public function promotionalAllocation(): BelongsTo
    {
        return $this->belongsTo(PromotionalAllocation::class);
    }
}
