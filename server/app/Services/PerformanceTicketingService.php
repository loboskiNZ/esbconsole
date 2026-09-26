<?php

namespace App\Services;

use App\Exceptions\PerformanceTicketingException;
use App\Models\GuestListEntry;
use App\Models\Performance;
use App\Models\PerformanceTicketingConfiguration;
use App\Models\PromotionalAllocation;
use App\Models\PurchaseOffer;
use App\Models\Ticket;
use App\Models\TicketingAuditEntry;
use App\Models\TicketPriceTier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PerformanceTicketingService
{
    public function __construct(
        private readonly PerformanceCapacityService $capacity,
    ) {}

    /**
     * @param  array{
     *     enabled: bool,
     *     capacity: ?int,
     *     currency: ?string,
     *     timezone: string,
     *     sales_open_at: ?string,
     *     sales_close_at: ?string,
     *     public_sales_enabled: bool,
     *     walk_in_sales_enabled: bool,
     *     interest_registration_enabled: bool,
     *     private_offers_enabled: bool,
     *     marketing_registration_enabled: bool,
     *     offer_validity_minutes: int,
     *     abandoned_checkout_reminder_minutes: int,
     *     complimentary_allocation: int,
     *     promotional_allocation: int,
     *     default_offer_tier: ?string,
     *     tiers: list<array{
     *         public_id: ?string,
     *         name: string,
     *         amount: string,
     *         currency: string,
     *         category: ?string,
     *         starts_at: ?string,
     *         ends_at: ?string,
     *         enabled: bool,
     *     }>,
     * }  $payload
     */
    public function save(Performance $performance, array $payload, ?User $actor): PerformanceTicketingConfiguration
    {
        return DB::transaction(function () use ($performance, $payload, $actor): PerformanceTicketingConfiguration {
            $configuration = PerformanceTicketingConfiguration::query()
                ->where('performance_id', $performance->id)
                ->lockForUpdate()
                ->first();

            $before = $configuration === null ? null : $this->auditSnapshot($configuration);

            if ($configuration === null) {
                $configuration = new PerformanceTicketingConfiguration([
                    'public_id' => (string) Str::uuid(),
                    'performance_id' => $performance->id,
                ]);
            }

            $configuration->fill([
                'enabled' => $payload['enabled'],
                'capacity' => $payload['capacity'],
                'currency' => $payload['currency'],
                'timezone' => $payload['timezone'],
                'sales_open_at' => $this->parseLocal($payload['sales_open_at'], $payload['timezone']),
                'sales_close_at' => $this->parseLocal($payload['sales_close_at'], $payload['timezone']),
                'public_sales_enabled' => $payload['public_sales_enabled'],
                'walk_in_sales_enabled' => $payload['walk_in_sales_enabled'],
                'interest_registration_enabled' => $payload['interest_registration_enabled'],
                'private_offers_enabled' => $payload['private_offers_enabled'],
                'marketing_registration_enabled' => $payload['marketing_registration_enabled'],
                'offer_validity_minutes' => $payload['offer_validity_minutes'],
                'abandoned_checkout_reminder_minutes' => $payload['abandoned_checkout_reminder_minutes'],
                'complimentary_allocation' => $payload['complimentary_allocation'],
                'promotional_allocation' => $payload['promotional_allocation'],
                'default_offer_price_tier_id' => null,
            ]);
            $configuration->save();

            $configuration->setRelation('performance', $performance);
            $this->assertAllocationsCoverExisting($performance, $payload['complimentary_allocation'], $payload['promotional_allocation']);
            if ($payload['capacity'] !== null) {
                $this->capacity->assertCapacityCovers($configuration, $payload['capacity']);
            }

            $tiers = $this->syncTiers($configuration, $payload['tiers'], $payload['timezone']);
            $defaultTier = $this->resolveDefaultTier($tiers, $payload['default_offer_tier']);
            $configuration->default_offer_price_tier_id = $defaultTier?->id;
            $configuration->save();

            $configuration->unsetRelation('priceTiers');
            TicketingAuditEntry::query()->create([
                'performance_id' => $performance->id,
                'user_id' => $actor?->id,
                'action' => 'ticketing_saved',
                'subject_type' => $configuration->getMorphClass(),
                'subject_id' => $configuration->id,
                'payload' => [
                    'before' => $before,
                    'after' => $this->auditSnapshot($configuration->fresh(['priceTiers'])),
                ],
            ]);

            return $configuration->fresh(['priceTiers', 'defaultOfferPriceTier']);
        });
    }

    /**
     * @param  list<array{
     *     public_id: ?string,
     *     name: string,
     *     amount: string,
     *     currency: string,
     *     category: ?string,
     *     starts_at: ?string,
     *     ends_at: ?string,
     *     enabled: bool,
     * }>  $rows
     * @return list<TicketPriceTier>
     */
    private function syncTiers(PerformanceTicketingConfiguration $configuration, array $rows, string $timezone): array
    {
        $existing = TicketPriceTier::query()
            ->where('performance_ticketing_configuration_id', $configuration->id)
            ->get()
            ->keyBy('public_id');

        $kept = [];
        $saved = [];

        foreach (array_values($rows) as $index => $row) {
            try {
                $amountMinor = Money::minorUnits($row['amount'], $row['currency']);
            } catch (InvalidArgumentException $exception) {
                throw new PerformanceTicketingException($exception->getMessage());
            }

            $publicId = $row['public_id'] ?: null;
            if ($publicId !== null) {
                $tier = $existing->get($publicId);
                if ($tier === null) {
                    throw new PerformanceTicketingException('A price tier does not belong to this performance.');
                }
            } else {
                $tier = new TicketPriceTier([
                    'public_id' => (string) Str::uuid(),
                    'performance_ticketing_configuration_id' => $configuration->id,
                ]);
            }

            $tier->fill([
                'name' => $row['name'],
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($row['currency']),
                'category' => $row['category'],
                'starts_at' => $this->parseLocal($row['starts_at'], $timezone),
                'ends_at' => $this->parseLocal($row['ends_at'], $timezone),
                'enabled' => $row['enabled'],
                'sort_order' => $index,
            ]);
            $tier->save();
            $kept[] = $tier->public_id;
            $saved[] = $tier;
        }

        $removed = $existing->reject(fn (TicketPriceTier $tier): bool => in_array($tier->public_id, $kept, true));

        foreach ($removed as $tier) {
            $referenced = PurchaseOffer::query()->where('ticket_price_tier_id', $tier->id)->exists()
                || Ticket::query()->where('ticket_price_tier_id', $tier->id)->exists();

            if ($referenced) {
                throw new PerformanceTicketingException(
                    'The '.$tier->name.' tier has offers or tickets and cannot be removed.'
                );
            }

            $tier->delete();
        }

        return $saved;
    }

    /**
     * @param  list<TicketPriceTier>  $tiers
     */
    private function resolveDefaultTier(array $tiers, ?string $index): ?TicketPriceTier
    {
        if ($index === null || $index === '' || ! ctype_digit($index)) {
            return null;
        }

        return $tiers[(int) $index] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(PerformanceTicketingConfiguration $configuration): array
    {
        $configuration->loadMissing('priceTiers');

        return [
            'enabled' => $configuration->enabled,
            'capacity' => $configuration->capacity,
            'currency' => $configuration->currency,
            'timezone' => $configuration->timezone,
            'sales_open_at' => $configuration->sales_open_at?->toIso8601String(),
            'sales_close_at' => $configuration->sales_close_at?->toIso8601String(),
            'public_sales_enabled' => $configuration->public_sales_enabled,
            'walk_in_sales_enabled' => $configuration->walk_in_sales_enabled,
            'interest_registration_enabled' => $configuration->interest_registration_enabled,
            'private_offers_enabled' => $configuration->private_offers_enabled,
            'marketing_registration_enabled' => $configuration->marketing_registration_enabled,
            'offer_validity_minutes' => $configuration->offer_validity_minutes,
            'abandoned_checkout_reminder_minutes' => $configuration->abandoned_checkout_reminder_minutes,
            'complimentary_allocation' => $configuration->complimentary_allocation,
            'promotional_allocation' => $configuration->promotional_allocation,
            'default_offer_price_tier_id' => $configuration->default_offer_price_tier_id,
            'tiers' => $configuration->priceTiers->map(fn (TicketPriceTier $tier): array => [
                'public_id' => $tier->public_id,
                'name' => $tier->name,
                'amount_minor' => $tier->amount_minor,
                'currency' => $tier->currency,
                'enabled' => $tier->enabled,
                'sort_order' => $tier->sort_order,
            ])->all(),
        ];
    }

    private function assertAllocationsCoverExisting(Performance $performance, int $complimentaryAllocation, int $promotionalAllocation): void
    {
        $guestQuantity = (int) GuestListEntry::query()->where('performance_id', $performance->id)->sum('quantity');
        if ($complimentaryAllocation < $guestQuantity) {
            throw new PerformanceTicketingException(
                'Complimentary allocation cannot be lower than guests already listed ('.$guestQuantity.').'
            );
        }

        $promoQuantity = (int) PromotionalAllocation::query()
            ->where('performance_id', $performance->id)
            ->where('status', '!=', PromotionalAllocation::STATUS_EXPIRED)
            ->sum('quantity');
        if ($promotionalAllocation < $promoQuantity) {
            throw new PerformanceTicketingException(
                'Promotional allocation cannot be lower than promotional tickets already created ('.$promoQuantity.').'
            );
        }
    }

    private function parseLocal(?string $value, string $timezone): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value, $timezone)->utc();
    }
}
