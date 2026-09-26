<?php

namespace App\Services;

use App\Exceptions\PerformanceTicketingException;
use App\Models\CheckIn;
use App\Models\GuestListEntry;
use App\Models\Performance;
use App\Models\PerformanceTicketingConfiguration;
use App\Models\PromotionalAllocation;
use App\Models\Ticket;
use App\Models\TicketingAuditEntry;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceAttendanceService
{
    public function __construct(
        private readonly PerformanceCapacityService $capacity,
    ) {}

    /**
     * @param  array{guest_name: string, email: ?string, quantity: int, category: ?string, notes: ?string}  $payload
     */
    public function addGuest(Performance $performance, array $payload, ?User $actor): GuestListEntry
    {
        return DB::transaction(function () use ($performance, $payload, $actor): GuestListEntry {
            $configuration = $this->lockedConfiguration($performance);

            if (! $configuration->enabled) {
                throw new PerformanceTicketingException('Enable ticketing before adding guests.');
            }

            $email = $payload['email'];
            if ($email !== null) {
                $duplicate = GuestListEntry::query()
                    ->where('performance_id', $performance->id)
                    ->whereRaw('lower(email) = ?', [strtolower($email)])
                    ->exists();

                if ($duplicate) {
                    throw new PerformanceTicketingException('That email is already on the guest list.');
                }
            }

            $currentGuests = (int) GuestListEntry::query()->where('performance_id', $performance->id)->sum('quantity');
            $nextGuests = $currentGuests + $payload['quantity'];

            if ($nextGuests > (int) $configuration->complimentary_allocation) {
                throw new PerformanceTicketingException('That guest party exceeds the complimentary allocation.');
            }

            $this->capacity->assertAdditionalFits($performance, $payload['quantity']);

            $entry = GuestListEntry::query()->create([
                'public_id' => (string) Str::uuid(),
                'performance_id' => $performance->id,
                'guest_name' => $payload['guest_name'],
                'email' => $email,
                'quantity' => $payload['quantity'],
                'category' => $payload['category'],
                'notes' => $payload['notes'],
                'created_by' => $actor?->id,
            ]);

            $this->audit($performance, $actor, 'guest_added', $entry->getMorphClass(), $entry->id, [
                'guest_name' => $entry->guest_name,
                'quantity' => $entry->quantity,
                'category' => $entry->category,
            ]);

            return $entry;
        });
    }

    public function removeGuest(Performance $performance, GuestListEntry $entry, ?User $actor): void
    {
        if ($entry->performance_id !== $performance->id) {
            throw new PerformanceTicketingException('That guest is not on this performance.');
        }

        if ($entry->checkIns()->exists()) {
            throw new PerformanceTicketingException('A guest with arrivals recorded cannot be removed.');
        }

        DB::transaction(function () use ($performance, $entry, $actor): void {
            $this->audit($performance, $actor, 'guest_removed', $entry->getMorphClass(), $entry->id, [
                'guest_name' => $entry->guest_name,
                'quantity' => $entry->quantity,
                'category' => $entry->category,
            ]);
            $entry->delete();
        });
    }

    /**
     * @param  array{label: ?string, holder_name: ?string, quantity: int}  $payload
     */
    public function addPromo(Performance $performance, array $payload, ?User $actor): PromotionalAllocation
    {
        return DB::transaction(function () use ($performance, $payload, $actor): PromotionalAllocation {
            $configuration = $this->lockedConfiguration($performance);

            if (! $configuration->enabled) {
                throw new PerformanceTicketingException('Enable ticketing before adding promotional tickets.');
            }

            $current = (int) PromotionalAllocation::query()
                ->where('performance_id', $performance->id)
                ->where('status', '!=', PromotionalAllocation::STATUS_EXPIRED)
                ->sum('quantity');

            if ($current + $payload['quantity'] > (int) $configuration->promotional_allocation) {
                throw new PerformanceTicketingException('That promotional ticket exceeds the promotional allocation.');
            }

            $allocation = PromotionalAllocation::query()->create([
                'public_id' => (string) Str::uuid(),
                'performance_id' => $performance->id,
                'label' => $payload['label'],
                'holder_name' => $payload['holder_name'],
                'status' => PromotionalAllocation::STATUS_INACTIVE,
                'quantity' => $payload['quantity'],
            ]);

            $this->audit($performance, $actor, 'promo_created', $allocation->getMorphClass(), $allocation->id, [
                'label' => $allocation->label,
                'quantity' => $allocation->quantity,
                'status' => $allocation->status,
            ]);

            return $allocation;
        });
    }

    public function updatePromo(Performance $performance, PromotionalAllocation $allocation, string $status, ?User $actor): PromotionalAllocation
    {
        if ($allocation->performance_id !== $performance->id) {
            throw new PerformanceTicketingException('That promotional ticket is not for this performance.');
        }

        if (! in_array($status, PromotionalAllocation::statuses(), true)) {
            throw new PerformanceTicketingException('Choose a valid promotional status.');
        }

        return DB::transaction(function () use ($performance, $allocation, $status, $actor): PromotionalAllocation {
            $this->lockedConfiguration($performance);
            $allocation->refresh();

            if ($allocation->checkedInQuantity() > 0 && $status !== PromotionalAllocation::STATUS_CLAIMED) {
                throw new PerformanceTicketingException('A promotional party with arrivals recorded stays claimed.');
            }

            $becomingClaimed = $allocation->status !== PromotionalAllocation::STATUS_CLAIMED
                && $status === PromotionalAllocation::STATUS_CLAIMED;

            if ($becomingClaimed) {
                $this->capacity->assertAdditionalFits($performance, (int) $allocation->quantity);
            }

            $allocation->status = $status;
            $allocation->claimed_at = $status === PromotionalAllocation::STATUS_CLAIMED ? ($allocation->claimed_at ?? now()) : null;
            $allocation->save();

            $this->audit($performance, $actor, 'promo_updated', $allocation->getMorphClass(), $allocation->id, [
                'status' => $allocation->status,
            ]);

            return $allocation;
        });
    }

    /**
     * @param  array{attendee_name: string, attendee_email: ?string, quantity: int}  $payload
     */
    public function createManualAdmission(Performance $performance, array $payload, ?User $actor): TicketOrder
    {
        return DB::transaction(function () use ($performance, $payload, $actor): TicketOrder {
            $configuration = $this->lockedConfiguration($performance);

            if (! $configuration->enabled) {
                throw new PerformanceTicketingException('Enable ticketing before creating a manual admission.');
            }

            if (! is_string($configuration->currency) || $configuration->currency === '') {
                throw new PerformanceTicketingException('Set a currency before creating a manual admission.');
            }

            $this->capacity->assertAdditionalFits($performance, $payload['quantity']);

            $order = TicketOrder::query()->create([
                'public_id' => (string) Str::uuid(),
                'performance_id' => $performance->id,
                'channel' => TicketOrder::CHANNEL_MANUAL,
                'status' => TicketOrder::STATUS_CONFIRMED,
                'payment_status' => TicketOrder::PAYMENT_NOT_APPLICABLE,
                'amount_minor' => 0,
                'currency' => $configuration->currency,
                'quantity' => $payload['quantity'],
                'buyer_name' => $payload['attendee_name'],
                'buyer_email' => $payload['attendee_email'],
            ]);

            for ($index = 0; $index < $payload['quantity']; $index++) {
                Ticket::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'ticket_order_id' => $order->id,
                    'performance_id' => $performance->id,
                    'amount_minor' => 0,
                    'currency' => $configuration->currency,
                    'status' => Ticket::STATUS_VALID,
                    'attendee_name' => $payload['attendee_name'],
                    'attendee_email' => $payload['attendee_email'],
                ]);
            }

            $this->audit($performance, $actor, 'manual_admission_created', $order->getMorphClass(), $order->id, [
                'attendee_name' => $payload['attendee_name'],
                'quantity' => $payload['quantity'],
                'channel' => TicketOrder::CHANNEL_MANUAL,
                'status' => TicketOrder::STATUS_CONFIRMED,
                'payment_status' => TicketOrder::PAYMENT_NOT_APPLICABLE,
            ]);

            return $order->load('tickets');
        });
    }

    /**
     * @return array{state: string, checked_in_at: ?Carbon, label: string, allocated: int, checked_in_quantity: int, not_yet_arrived: int}
     */
    public function checkInToken(Performance $performance, string $token, int $quantity, ?User $actor): array
    {
        $token = trim($token);

        if ($token === '') {
            throw new PerformanceTicketingException('Enter a ticket or choose a guest.');
        }

        if ($quantity < 1) {
            throw new PerformanceTicketingException('Check in at least one person.');
        }

        $ticket = Ticket::query()
            ->where('performance_id', $performance->id)
            ->where('public_id', $token)
            ->first();

        if ($ticket !== null) {
            return $this->checkInTicket($performance, $ticket, $actor);
        }

        $guest = GuestListEntry::query()
            ->where('performance_id', $performance->id)
            ->where('public_id', $token)
            ->first();

        if ($guest !== null) {
            return $this->checkInParty($performance, $guest, $quantity, $actor);
        }

        $promo = PromotionalAllocation::query()
            ->where('performance_id', $performance->id)
            ->where('public_id', $token)
            ->first();

        if ($promo !== null) {
            return $this->checkInParty($performance, $promo, $quantity, $actor);
        }

        throw new PerformanceTicketingException('That ticket was not found for this performance.');
    }

    /**
     * @return array{state: string, checked_in_at: ?Carbon, label: string, allocated: int, checked_in_quantity: int, not_yet_arrived: int}
     */
    private function checkInTicket(Performance $performance, Ticket $ticket, ?User $actor): array
    {
        return DB::transaction(function () use ($performance, $ticket, $actor): array {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $locked->load('order', 'checkIn');
            $label = $locked->attendee_name ?: 'Ticket';

            if ($locked->status !== Ticket::STATUS_VALID || $locked->order?->status !== TicketOrder::STATUS_CONFIRMED) {
                throw new PerformanceTicketingException('That ticket is not valid for entry.');
            }

            if ($locked->checkIn !== null) {
                return $this->arrivalResult('already', $locked->checkIn->checked_in_at, $label, 1, 1);
            }

            $checkIn = $this->createCheckIn($performance, [
                'ticket_id' => $locked->id,
                'quantity' => 1,
            ], $actor);

            $this->audit($performance, $actor, 'check_in', $locked->getMorphClass(), $locked->id, [
                'ticket' => $locked->public_id,
                'quantity' => 1,
            ]);

            return $this->arrivalResult('checked_in', $checkIn->checked_in_at, $label, 1, 1);
        });
    }

    /**
     * @return array{state: string, checked_in_at: ?Carbon, label: string, allocated: int, checked_in_quantity: int, not_yet_arrived: int}
     */
    private function checkInParty(Performance $performance, GuestListEntry|PromotionalAllocation $party, int $quantity, ?User $actor): array
    {
        return DB::transaction(function () use ($performance, $party, $quantity, $actor): array {
            if ($party instanceof PromotionalAllocation && $party->status !== PromotionalAllocation::STATUS_CLAIMED) {
                throw new PerformanceTicketingException('That promotional ticket is not claimed.');
            }

            $locked = $party instanceof GuestListEntry
                ? GuestListEntry::query()->whereKey($party->id)->lockForUpdate()->firstOrFail()
                : PromotionalAllocation::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();

            $label = $locked instanceof GuestListEntry
                ? $locked->guest_name
                : ($locked->holder_name ?: ($locked->label ?: 'Promotional ticket'));
            $partyColumn = $locked instanceof GuestListEntry ? 'guest_list_entry_id' : 'promotional_allocation_id';
            $already = (int) CheckIn::query()
                ->where($partyColumn, $locked->id)
                ->lockForUpdate()
                ->sum('quantity');
            $allocated = (int) $locked->quantity;
            $remaining = $allocated - $already;
            $firstAt = $locked->checkIns()->orderBy('checked_in_at')->value('checked_in_at');

            if ($remaining < 1) {
                return $this->arrivalResult('already', $firstAt ? Carbon::parse($firstAt) : null, $label, $allocated, $already);
            }

            if ($quantity > $remaining) {
                throw new PerformanceTicketingException('Only '.$remaining.' '.($remaining === 1 ? 'place is' : 'places are').' still to arrive.');
            }

            $checkIn = $this->createCheckIn($performance, [
                'guest_list_entry_id' => $locked instanceof GuestListEntry ? $locked->id : null,
                'promotional_allocation_id' => $locked instanceof PromotionalAllocation ? $locked->id : null,
                'quantity' => $quantity,
            ], $actor);

            $this->audit($performance, $actor, 'check_in', $locked->getMorphClass(), $locked->id, [
                'quantity' => $quantity,
                'allocated' => $allocated,
            ]);

            return $this->arrivalResult('checked_in', $checkIn->checked_in_at, $label, $allocated, $already + $quantity);
        });
    }

    /**
     * @return array{state: string, checked_in_at: ?Carbon, label: string, allocated: int, checked_in_quantity: int, not_yet_arrived: int}
     */
    private function arrivalResult(string $state, ?Carbon $checkedInAt, string $label, int $allocated, int $checkedInQuantity): array
    {
        return [
            'state' => $state,
            'checked_in_at' => $checkedInAt,
            'label' => $label,
            'allocated' => $allocated,
            'checked_in_quantity' => $checkedInQuantity,
            'not_yet_arrived' => max(0, $allocated - $checkedInQuantity),
        ];
    }

    /**
     * @param  array{ticket_id?: int|null, guest_list_entry_id?: int|null, promotional_allocation_id?: int|null, quantity: int}  $subject
     */
    private function createCheckIn(Performance $performance, array $subject, ?User $actor): CheckIn
    {
        return CheckIn::query()->create([
            'performance_id' => $performance->id,
            'ticket_id' => $subject['ticket_id'] ?? null,
            'guest_list_entry_id' => $subject['guest_list_entry_id'] ?? null,
            'promotional_allocation_id' => $subject['promotional_allocation_id'] ?? null,
            'quantity' => $subject['quantity'],
            'checked_in_at' => now(),
            'checked_in_by' => $actor?->id,
        ]);
    }

    private function lockedConfiguration(Performance $performance): PerformanceTicketingConfiguration
    {
        $configuration = PerformanceTicketingConfiguration::query()
            ->where('performance_id', $performance->id)
            ->lockForUpdate()
            ->first();

        if ($configuration === null) {
            throw new PerformanceTicketingException('Configure ticketing for this performance first.');
        }

        $configuration->setRelation('performance', $performance);

        return $configuration;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(Performance $performance, ?User $actor, string $action, string $subjectType, int $subjectId, array $payload): void
    {
        TicketingAuditEntry::query()->create([
            'performance_id' => $performance->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
        ]);
    }
}
