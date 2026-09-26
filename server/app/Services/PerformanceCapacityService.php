<?php

namespace App\Services;

use App\Exceptions\PerformanceTicketingException;
use App\Models\CheckIn;
use App\Models\GuestListEntry;
use App\Models\Performance;
use App\Models\PerformanceTicketingConfiguration;
use App\Models\PromotionalAllocation;
use App\Models\Ticket;
use App\Models\TicketOrder;

class PerformanceCapacityService
{
    /**
     * Confirmed admissions consume capacity. Unused guest and promo caps do not.
     * Check-in does not free a seat.
     *
     * @return array{
     *     configured: bool,
     *     enabled: bool,
     *     currency: ?string,
     *     capacity: ?int,
     *     paid_tickets: int,
     *     manual_admissions: int,
     *     walk_ins: int,
     *     guest_quantity: int,
     *     complimentary_allocation: int,
     *     promo_claimed: int,
     *     promotional_allocation: int,
     *     seats_held: int,
     *     checked_in: int,
     *     not_yet_arrived: int,
     *     remaining: ?int,
     * }
     */
    public function snapshot(Performance $performance): array
    {
        $configuration = $performance->relationLoaded('ticketingConfiguration')
            ? $performance->ticketingConfiguration
            : $performance->ticketingConfiguration()->first();

        $confirmedTickets = $this->confirmedTickets($performance->id);
        $paidTickets = $this->confirmedTickets($performance->id, TicketOrder::PAYMENT_PAID);
        $manualAdmissions = $this->confirmedTickets($performance->id, TicketOrder::PAYMENT_NOT_APPLICABLE, TicketOrder::CHANNEL_MANUAL);
        $walkIns = $this->confirmedTickets($performance->id, null, TicketOrder::CHANNEL_WALK_IN);
        $guestQuantity = (int) GuestListEntry::query()->where('performance_id', $performance->id)->sum('quantity');
        $promoClaimed = (int) PromotionalAllocation::query()
            ->where('performance_id', $performance->id)
            ->where('status', PromotionalAllocation::STATUS_CLAIMED)
            ->sum('quantity');
        $seatsHeld = $confirmedTickets + $guestQuantity + $promoClaimed;
        $checkedIn = (int) CheckIn::query()->where('performance_id', $performance->id)->sum('quantity');
        $capacity = $configuration?->capacity;

        return [
            'configured' => $configuration !== null,
            'enabled' => (bool) ($configuration->enabled ?? false),
            'currency' => $configuration?->currency,
            'capacity' => $capacity,
            'paid_tickets' => $paidTickets,
            'manual_admissions' => $manualAdmissions,
            'walk_ins' => $walkIns,
            'guest_quantity' => $guestQuantity,
            'complimentary_allocation' => (int) ($configuration->complimentary_allocation ?? 0),
            'promo_claimed' => $promoClaimed,
            'promotional_allocation' => (int) ($configuration->promotional_allocation ?? 0),
            'seats_held' => $seatsHeld,
            'checked_in' => $checkedIn,
            'not_yet_arrived' => max(0, $seatsHeld - $checkedIn),
            'remaining' => $capacity === null ? null : $capacity - $seatsHeld,
        ];
    }

    public function assertCapacityCovers(PerformanceTicketingConfiguration $configuration, int $capacity): void
    {
        $performance = $configuration->performance;
        $performance?->unsetRelation('ticketingConfiguration');
        $held = $performance === null ? 0 : $this->snapshot($performance)['seats_held'];

        if ($capacity < $held) {
            throw new PerformanceTicketingException(
                'Capacity cannot be lower than seats already held ('.$held.').'
            );
        }
    }

    public function assertAdditionalFits(Performance $performance, int $additional): void
    {
        if ($additional < 1) {
            throw new PerformanceTicketingException('Quantity must be at least 1.');
        }

        $performance->unsetRelation('ticketingConfiguration');
        $remaining = $this->snapshot($performance)['remaining'];

        if ($remaining === null) {
            throw new PerformanceTicketingException('Set a venue capacity before allocating seats.');
        }

        if ($additional > $remaining) {
            throw new PerformanceTicketingException('That would exceed the remaining capacity ('.$remaining.').');
        }
    }

    private function confirmedTickets(int $performanceId, ?string $paymentStatus = null, ?string $channel = null): int
    {
        return Ticket::query()
            ->where('performance_id', $performanceId)
            ->where('status', Ticket::STATUS_VALID)
            ->whereHas('order', function ($query) use ($paymentStatus, $channel): void {
                $query->where('status', TicketOrder::STATUS_CONFIRMED);

                if ($paymentStatus !== null) {
                    $query->where('payment_status', $paymentStatus);
                }

                if ($channel !== null) {
                    $query->where('channel', $channel);
                }
            })
            ->count();
    }
}
