<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Performance;
use App\Models\PerformanceTicketingConfiguration;
use App\Models\Show;
use App\Models\Ticket;
use App\Models\TicketingAuditEntry;
use App\Models\TicketOrder;
use App\Models\TicketPriceTier;
use App\Models\User;
use App\Services\PerformanceCapacityService;
use App\Services\StudioPerformanceService;
use App\Services\StudioShowService;
use App\Services\Ticketing\PrivateOfferCampaign;
use App\Services\Ticketing\PrivateOfferLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioPerformanceTicketingTest extends TestCase
{
    use AssignsStudioRoles;
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal.band_id' => 1]);
        $this->ensurePortalBand();
    }

    public function test_performance_without_ticketing_keeps_the_existing_page(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();

        $this->actingAs($director)
            ->get(route('studio.performances.show', $performance))
            ->assertOk()
            ->assertSee('Ticketing is off for this performance', false)
            ->assertSee('Set up ticketing', false)
            ->assertDontSee('Door / Check-in', false)
            ->assertSee('No availability records yet.', false);

        $this->assertSame(0, PerformanceTicketingConfiguration::query()->count());
        $this->assertFalse(Schema::hasColumn('performances', 'currency'));
        $this->assertFalse(Schema::hasColumn('performances', 'capacity'));
    }

    public function test_musician_cannot_configure_ticketing(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $performance = $this->seedPerformance();

        $this->actingAs($musician)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertForbidden();

        $this->actingAs($musician)
            ->get(route('studio.performances.door', $performance))
            ->assertForbidden();
    }

    public function test_director_can_enable_ticketing_with_a_currency_and_tier(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();

        $this->saveTicketing($director, $performance, [
            'currency' => 'GBP',
            'timezone' => 'Europe/London',
            'tiers' => [[
                'name' => 'General admission',
                'amount' => '22.00',
                'currency' => 'GBP',
                'enabled' => '1',
            ]],
        ]);

        $configuration = PerformanceTicketingConfiguration::query()->where('performance_id', $performance->id)->firstOrFail();
        $tier = TicketPriceTier::query()->where('performance_ticketing_configuration_id', $configuration->id)->firstOrFail();

        $this->assertTrue($configuration->enabled);
        $this->assertSame('GBP', $configuration->currency);
        $this->assertSame(100, $configuration->capacity);
        $this->assertSame(1440, $configuration->offer_validity_minutes);
        $this->assertSame(240, $configuration->abandoned_checkout_reminder_minutes);
        $this->assertSame(2200, $tier->amount_minor);
        $this->assertSame('GBP', $tier->currency);
        $this->assertSame(1, PerformanceTicketingConfiguration::query()->count());
        $this->assertTrue(TicketingAuditEntry::query()->where('action', 'ticketing_saved')->exists());
    }

    public function test_money_is_stored_in_minor_units_for_more_than_one_currency(): void
    {
        $director = $this->createDirectorUser();
        $nzd = $this->seedPerformance();
        $jpy = $this->seedPerformance();

        $this->saveTicketing($director, $nzd, [
            'currency' => 'NZD',
            'timezone' => 'Pacific/Auckland',
            'tiers' => [[
                'name' => 'Launch',
                'amount' => '19.50',
                'currency' => 'NZD',
                'enabled' => '1',
            ]],
        ]);
        $this->saveTicketing($director, $jpy, [
            'currency' => 'JPY',
            'timezone' => 'Asia/Tokyo',
            'tiers' => [[
                'name' => 'Door',
                'amount' => '500',
                'currency' => 'JPY',
                'enabled' => '1',
            ]],
        ]);

        $this->assertSame(1950, TicketPriceTier::query()->where('currency', 'NZD')->firstOrFail()->amount_minor);
        $this->assertSame(500, TicketPriceTier::query()->where('currency', 'JPY')->firstOrFail()->amount_minor);
    }

    public function test_changing_configuration_currency_does_not_rewrite_existing_amounts(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'currency' => 'NZD',
            'complimentary_allocation' => 0,
            'promotional_allocation' => 0,
        ]);

        $this->actingAs($director)->post(route('studio.performances.manual-admissions.store', $performance), [
            'attendee_name' => 'Ada',
            'quantity' => 1,
        ])->assertRedirect();

        $tier = TicketPriceTier::query()->firstOrFail();
        $order = TicketOrder::query()->firstOrFail();

        $this->saveTicketing($director, $performance, [
            'currency' => 'EUR',
            'complimentary_allocation' => 0,
            'promotional_allocation' => 0,
            'tiers' => [[
                'public_id' => $tier->public_id,
                'name' => 'General admission',
                'amount' => '19.50',
                'currency' => 'NZD',
                'enabled' => '0',
            ]],
        ]);

        $tier->refresh();
        $order->refresh();

        $this->assertSame('EUR', $performance->ticketingConfiguration()->firstOrFail()->currency);
        $this->assertSame(1950, $tier->amount_minor);
        $this->assertSame('NZD', $tier->currency);
        $this->assertFalse($tier->enabled);
        $this->assertSame(0, $order->amount_minor);
        $this->assertSame('NZD', $order->currency);
        $this->assertSame(TicketOrder::STATUS_CONFIRMED, $order->status);
        $this->assertSame(TicketOrder::PAYMENT_NOT_APPLICABLE, $order->payment_status);
        $this->assertSame(TicketOrder::CHANNEL_MANUAL, $order->channel);
    }

    public function test_guest_and_claimed_promo_consume_capacity_but_unused_caps_do_not(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'capacity' => 10,
            'complimentary_allocation' => 6,
            'promotional_allocation' => 3,
        ]);

        $this->assertSame(10, $this->remaining($performance));

        $this->actingAs($director)->post(route('studio.performances.guests.store', $performance), [
            'guest_name' => 'Jamie',
            'quantity' => 4,
            'category' => 'Guest artist',
        ])->assertRedirect();

        $this->assertSame(6, $this->remaining($performance));

        $this->actingAs($director)->post(route('studio.performances.guests.store', $performance), [
            'guest_name' => 'Too many',
            'quantity' => 3,
        ])->assertSessionHas('ticketing_error');

        $this->actingAs($director)->post(route('studio.performances.promos.store', $performance), [
            'label' => 'Festival',
            'holder_name' => 'Press',
            'quantity' => 2,
        ])->assertRedirect();

        $this->assertSame(6, $this->remaining($performance));

        $promo = $performance->promotionalAllocations()->firstOrFail();
        $this->actingAs($director)->patch(route('studio.performances.promos.update', [$performance, $promo]), [
            'status' => 'claimed',
        ])->assertRedirect();

        $this->assertSame(4, $this->remaining($performance));
    }

    public function test_capacity_cannot_be_lowered_below_allocated_seats(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'capacity' => 10,
            'complimentary_allocation' => 4,
            'promotional_allocation' => 0,
        ]);
        $this->actingAs($director)->post(route('studio.performances.guests.store', $performance), [
            'guest_name' => 'Jamie',
            'quantity' => 4,
        ])->assertRedirect();

        $this->actingAs($director)
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'capacity' => 3,
                'complimentary_allocation' => 4,
                'promotional_allocation' => 0,
            ]))
            ->assertSessionHas('ticketing_error');

        $this->assertSame(10, $performance->ticketingConfiguration()->firstOrFail()->capacity);
    }

    public function test_manual_admission_is_confirmed_without_payment_and_consumes_capacity(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'capacity' => 5,
            'complimentary_allocation' => 0,
            'promotional_allocation' => 0,
        ]);

        $this->actingAs($director)->post(route('studio.performances.manual-admissions.store', $performance), [
            'attendee_name' => 'Nia',
            'quantity' => 2,
        ])->assertRedirect()->assertSessionHas('manual_admission_saved');

        $order = TicketOrder::query()->firstOrFail();
        $this->assertSame(TicketOrder::CHANNEL_MANUAL, $order->channel);
        $this->assertSame(TicketOrder::STATUS_CONFIRMED, $order->status);
        $this->assertSame(TicketOrder::PAYMENT_NOT_APPLICABLE, $order->payment_status);
        $this->assertSame(2, $order->tickets()->count());
        $this->assertSame(0, $order->tickets()->whereHas('checkIn')->count());
        $this->assertSame(3, $this->remaining($performance));
        $this->assertTrue(TicketingAuditEntry::query()->where('action', 'manual_admission_created')->exists());
    }

    public function test_guest_party_supports_partial_check_in(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'capacity' => 10,
            'complimentary_allocation' => 4,
            'promotional_allocation' => 0,
        ]);
        $this->actingAs($director)->post(route('studio.performances.guests.store', $performance), [
            'guest_name' => 'Riley',
            'quantity' => 4,
        ])->assertRedirect();

        $guest = $performance->guestListEntries()->firstOrFail();

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $guest->public_id,
            'quantity' => 2,
        ])->assertRedirect()->assertSessionHas('door_success');

        $guest->refresh();
        $this->assertSame(4, $guest->quantity);
        $this->assertSame(2, $guest->checkedInQuantity());
        $this->assertSame(6, $this->remaining($performance));

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $guest->public_id,
            'quantity' => 2,
        ])->assertSessionHas('door_success');

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $guest->public_id,
            'quantity' => 1,
        ])->assertSessionHas('door_notice');

        $this->assertSame(2, $guest->checkIns()->count());
        $this->assertSame(4, $guest->checkedInQuantity());
    }

    public function test_promotional_party_supports_partial_check_in(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'capacity' => 10,
            'complimentary_allocation' => 0,
            'promotional_allocation' => 4,
        ]);
        $this->actingAs($director)->post(route('studio.performances.promos.store', $performance), [
            'label' => 'Press',
            'holder_name' => 'Alex',
            'quantity' => 4,
        ])->assertRedirect();

        $promo = $performance->promotionalAllocations()->firstOrFail();
        $this->actingAs($director)->patch(route('studio.performances.promos.update', [$performance, $promo]), [
            'status' => 'claimed',
        ])->assertRedirect();

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $promo->public_id,
            'quantity' => 2,
        ])->assertRedirect()->assertSessionHas('door_success');

        $promo->refresh();
        $this->assertSame(4, $promo->quantity);
        $this->assertSame(2, $promo->checkedInQuantity());
        $this->assertSame(6, $this->remaining($performance));

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $promo->public_id,
            'quantity' => 3,
        ])->assertSessionHas('door_error');

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $promo->public_id,
            'quantity' => 2,
        ])->assertSessionHas('door_success');

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $promo->public_id,
            'quantity' => 1,
        ])->assertSessionHas('door_notice');

        $this->assertSame(2, $promo->checkIns()->count());
        $this->assertSame(4, $promo->checkedInQuantity());
    }

    public function test_door_search_finds_a_manual_ticket_by_name(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'capacity' => 10,
            'complimentary_allocation' => 4,
            'promotional_allocation' => 0,
        ]);
        $this->actingAs($director)->post(route('studio.performances.guests.store', $performance), [
            'guest_name' => 'Riley',
            'quantity' => 4,
        ])->assertRedirect();
        $this->actingAs($director)->post(route('studio.performances.manual-admissions.store', $performance), [
            'attendee_name' => 'Nia',
            'quantity' => 1,
        ])->assertRedirect();

        $this->actingAs($director)
            ->get(route('studio.performances.door', ['performance' => $performance, 'q' => 'Nia']))
            ->assertOk()
            ->assertSee('Name, email, or ticket code')
            ->assertSee('Nia')
            ->assertDontSee('Riley');
    }

    public function test_door_page_offers_qr_scanning_as_an_input_for_the_existing_check_in(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'capacity' => 10,
            'complimentary_allocation' => 0,
            'promotional_allocation' => 0,
        ]);
        $this->actingAs($director)->post(route('studio.performances.manual-admissions.store', $performance), [
            'attendee_name' => 'Ada',
            'quantity' => 1,
        ])->assertRedirect();

        $ticket = Ticket::query()->firstOrFail();

        $this->actingAs($director)
            ->get(route('studio.performances.door', $performance))
            ->assertOk()
            ->assertSee('id="door-check-in"', false)
            ->assertSee('id="door-scan-start"', false)
            ->assertSee('Scan QR', false)
            ->assertSee('id="door-scan-cancel"', false)
            ->assertSee('Cancel scan', false)
            ->assertSee('id="door-scan-video"', false)
            ->assertSee('playsinline', false)
            ->assertSee('id="door-token"', false)
            ->assertSee('name="token"', false)
            ->assertSee('Scan or paste the ticket code', false)
            ->assertSee('Check in', false)
            ->assertSee(route('studio.performances.door.check-in', $performance), false)
            ->assertDontSee('door/scan', false);

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $ticket->public_id,
        ])->assertRedirect()->assertSessionHas('door_success');

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => 'not-a-ticket',
        ])->assertRedirect()->assertSessionHas('door_error', 'That ticket was not found for this performance.');
    }

    public function test_ticket_cannot_be_checked_in_twice_or_when_cancelled(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'capacity' => 5,
            'complimentary_allocation' => 0,
            'promotional_allocation' => 0,
        ]);
        $this->actingAs($director)->post(route('studio.performances.manual-admissions.store', $performance), [
            'attendee_name' => 'Sam',
            'quantity' => 1,
        ])->assertRedirect();

        $ticket = Ticket::query()->firstOrFail();

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $ticket->public_id,
            'quantity' => 1,
        ])->assertSessionHas('door_success');

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $performance), [
            'token' => $ticket->public_id,
            'quantity' => 1,
        ])->assertSessionHas('door_notice');

        $this->assertSame(1, $ticket->checkIn()->count());

        $second = $this->seedPerformance();
        $this->saveTicketing($director, $second, [
            'capacity' => 5,
            'complimentary_allocation' => 0,
            'promotional_allocation' => 0,
        ]);
        $this->actingAs($director)->post(route('studio.performances.manual-admissions.store', $second), [
            'attendee_name' => 'Cancelled',
            'quantity' => 1,
        ])->assertRedirect();
        $cancelled = Ticket::query()->where('performance_id', $second->id)->firstOrFail();
        $cancelled->update(['status' => Ticket::STATUS_CANCELLED]);

        $this->actingAs($director)->post(route('studio.performances.door.check-in', $second), [
            'token' => $cancelled->public_id,
            'quantity' => 1,
        ])->assertSessionHas('door_error');
    }

    public function test_check_in_times_display_in_each_performance_timezone_without_changing_storage(): void
    {
        $director = $this->createDirectorUser();
        $newYork = $this->seedPerformance();
        $madrid = $this->seedPerformance();

        $this->saveTicketing($director, $newYork, [
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'capacity' => 5,
            'complimentary_allocation' => 1,
            'promotional_allocation' => 1,
            'tiers' => [[
                'name' => 'General admission',
                'amount' => '20.00',
                'currency' => 'USD',
                'enabled' => '1',
            ]],
        ]);
        $this->saveTicketing($director, $madrid, [
            'timezone' => 'Europe/Madrid',
            'currency' => 'EUR',
            'capacity' => 5,
            'complimentary_allocation' => 0,
            'promotional_allocation' => 0,
            'tiers' => [[
                'name' => 'General admission',
                'amount' => '20.00',
                'currency' => 'EUR',
                'enabled' => '1',
            ]],
        ]);

        $this->actingAs($director)->post(route('studio.performances.guests.store', $newYork), [
            'guest_name' => 'Jordan',
            'quantity' => 1,
        ])->assertRedirect();
        $guest = $newYork->guestListEntries()->firstOrFail();

        $this->actingAs($director)->post(route('studio.performances.promos.store', $newYork), [
            'label' => 'Press',
            'holder_name' => 'Alex',
            'quantity' => 1,
        ])->assertRedirect();
        $promo = $newYork->promotionalAllocations()->firstOrFail();
        $this->actingAs($director)->patch(route('studio.performances.promos.update', [$newYork, $promo]), [
            'status' => 'claimed',
        ])->assertRedirect();

        $this->actingAs($director)->post(route('studio.performances.manual-admissions.store', $madrid), [
            'attendee_name' => 'Nia',
            'quantity' => 1,
        ])->assertRedirect();
        $ticket = Ticket::query()->where('performance_id', $madrid->id)->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-07-15 17:17:00', 'UTC'));

        try {
            $this->actingAs($director)->post(route('studio.performances.door.check-in', $newYork), [
                'token' => $guest->public_id,
                'quantity' => 1,
            ])->assertSessionHas('door_success');
            $this->actingAs($director)->post(route('studio.performances.door.check-in', $newYork), [
                'token' => $promo->public_id,
                'quantity' => 1,
            ])->assertSessionHas('door_success');
            $this->actingAs($director)->post(route('studio.performances.door.check-in', $madrid), [
                'token' => $ticket->public_id,
                'quantity' => 1,
            ])->assertSessionHas('door_success');

            $storedBeforeDisplay = $this->storedCheckInInstants($guest->id, $promo->id, $ticket->id);

            $this->actingAs($director)
                ->get(route('studio.performances.door', $newYork))
                ->assertOk()
                ->assertSee('Arrival times are shown in America/New_York', false)
                ->assertSee('Already checked in · 15 Jul 2026, 13:17', false)
                ->assertDontSee('15 Jul 2026, 17:17', false)
                ->assertDontSee('UTC', false);

            $this->actingAs($director)
                ->get(route('studio.performances.door', $madrid))
                ->assertOk()
                ->assertSee('Arrival times are shown in Europe/Madrid', false)
                ->assertSee('Already checked in · 15 Jul 2026, 19:17', false)
                ->assertDontSee('15 Jul 2026, 17:17', false)
                ->assertDontSee('UTC', false);

            $this->actingAs($director)
                ->get(route('studio.performances.ticketing.edit', $madrid))
                ->assertOk()
                ->assertSee('checked in · 15 Jul 2026, 19:17', false)
                ->assertDontSee('15 Jul 2026, 17:17', false);

            Carbon::setTestNow(Carbon::parse('2026-07-15 18:05:00', 'UTC'));

            $guestNotice = $this->actingAs($director)
                ->from(route('studio.performances.door', $newYork))
                ->post(route('studio.performances.door.check-in', $newYork), [
                    'token' => $guest->public_id,
                    'quantity' => 1,
                ]);
            $guestNotice->assertSessionHas('door_notice');
            $this->assertStringContainsString('Already checked in · 15 Jul 2026, 13:17', (string) $guestNotice->getSession()->get('door_notice'));
            $this->assertStringNotContainsString('15 Jul 2026, 14:05', (string) $guestNotice->getSession()->get('door_notice'));

            $ticketNotice = $this->actingAs($director)
                ->from(route('studio.performances.door', $madrid))
                ->post(route('studio.performances.door.check-in', $madrid), [
                    'token' => $ticket->public_id,
                    'quantity' => 1,
                ]);
            $ticketNotice->assertSessionHas('door_notice');
            $this->assertStringContainsString('Already checked in · 15 Jul 2026, 19:17', (string) $ticketNotice->getSession()->get('door_notice'));

            $storedAfterDisplay = $this->storedCheckInInstants($guest->id, $promo->id, $ticket->id);

            $this->assertSame($storedBeforeDisplay, $storedAfterDisplay);
            $this->assertSame(1, CheckIn::query()->where('guest_list_entry_id', $guest->id)->count());
            $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
            $this->assertSame([
                '2026-07-15 17:17:00',
                '2026-07-15 17:17:00',
                '2026-07-15 17:17:00',
            ], $storedAfterDisplay);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_ticketing_timezone_persists_and_rejects_values_that_are_not_iana_identifiers(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();

        $this->saveTicketing($director, $performance, [
            'timezone' => 'Europe/Madrid',
            'sales_open_at' => '2026-07-15T19:17',
            'sales_close_at' => '2026-07-15T22:00',
        ]);

        $configuration = $performance->ticketingConfiguration()->firstOrFail();
        $storedOpen = $configuration->sales_open_at->format('Y-m-d H:i:s');

        $this->assertSame('Europe/Madrid', $configuration->timezone);
        $this->assertSame('2026-07-15 17:17:00', $configuration->sales_open_at->copy()->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-15T19:17', $configuration->localInput($configuration->sales_open_at));
        $this->assertSame('15 Jul 2026, 19:17', $configuration->formatInstant($configuration->sales_open_at));
        $this->assertSame($storedOpen, $configuration->sales_open_at->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $configuration->sales_open_at->timezoneName);

        $this->actingAs($director)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertOk()
            ->assertSee('value="Europe/Madrid" selected', false)
            ->assertSee('value="2026-07-15T19:17"', false)
            ->assertSee('value="2026-07-15T22:00"', false);

        foreach (['Not/AZone', '+12:00', 'NZST'] as $timezone) {
            $this->actingAs($director)
                ->from(route('studio.performances.ticketing.edit', $performance))
                ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                    'timezone' => $timezone,
                    'sales_open_at' => '2026-07-15T19:17',
                    'sales_close_at' => '2026-07-15T22:00',
                ]))
                ->assertSessionHasErrors('timezone');
        }

        $configuration->refresh();
        $this->assertSame('Europe/Madrid', $configuration->timezone);
        $this->assertSame('2026-07-15 17:17:00', $configuration->sales_open_at->copy()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_director_home_links_to_ticketing_and_reuses_capacity_counts(): void
    {
        $director = $this->createDirectorUser();
        $soon = $this->seedPerformance();
        $later = $this->seedPerformance();
        $soon->update([
            'performance_date' => now()->toDateString(),
            'location_name' => 'Grainstore',
        ]);
        $later->update([
            'performance_date' => now()->addDays(21)->toDateString(),
            'location_name' => 'Wellington',
        ]);

        $this->saveTicketing($director, $soon, [
            'capacity' => 10,
            'complimentary_allocation' => 4,
            'promotional_allocation' => 0,
        ]);
        $this->saveTicketing($director, $later, [
            'capacity' => 10,
            'complimentary_allocation' => 4,
            'promotional_allocation' => 0,
        ]);
        $this->actingAs($director)->post(route('studio.performances.guests.store', $soon), [
            'guest_name' => 'Jordan',
            'quantity' => 2,
        ])->assertRedirect();

        $snapshot = app(PerformanceCapacityService::class)->snapshot($soon->fresh());

        $home = $this->actingAs($director)->get(route('studio'));
        $home->assertOk()
            ->assertSee($soon->show->name, false)
            ->assertSee('Ticketing on · Allocated '.$snapshot['seats_held'].' · Remaining '.$snapshot['remaining'].' · Checked in '.$snapshot['checked_in'], false)
            ->assertSee('href="'.route('studio.performances.ticketing.edit', $soon).'"', false)
            ->assertSee('Door / Check-in', false)
            ->assertSee('href="'.route('studio.performances.door', $soon).'"', false)
            ->assertSee('href="'.route('studio.performances.ticketing.edit', $later).'"', false)
            ->assertDontSee('href="'.route('studio.performances.door', $later).'"', false);

        $this->actingAs($director)
            ->get(route('studio.performances.ticketing.edit', $soon))
            ->assertOk()
            ->assertSee($soon->eventContextLabel(), false)
            ->assertSee('Studio Home', false)
            ->assertSee('href="'.route('studio.performances.show', $soon).'"', false)
            ->assertSee('Door / Check-in', false);

        $this->actingAs($director)
            ->get(route('studio.performances.show', $soon))
            ->assertOk()
            ->assertSee('Ticketing', false)
            ->assertSee('Door / Check-in', false)
            ->assertSee('href="'.route('studio.performances.door', $soon).'"', false);

        $this->actingAs($director)
            ->get(route('studio.performances.door', $soon))
            ->assertOk()
            ->assertSee($soon->show->name, false)
            ->assertSee($soon->formattedPerformanceDate(), false)
            ->assertSee('Grainstore', false)
            ->assertSee('Studio Home', false)
            ->assertSee('href="'.route('studio.performances.ticketing.edit', $soon).'"', false)
            ->assertSee('href="'.route('studio.performances.show', $soon).'"', false);
    }

    public function test_live_performance_without_ticketing_offers_setup_and_rehearsals_stay_plain(): void
    {
        $director = $this->createDirectorUser();
        $live = $this->seedPerformance();
        $rehearsal = $this->seedPerformance();
        $live->update(['performance_date' => now()->addDays(3)->toDateString()]);
        $rehearsal->update([
            'performance_type' => Performance::TYPE_REHEARSAL,
            'performance_date' => now()->addDays(4)->toDateString(),
        ]);

        $home = $this->actingAs($director)->get(route('studio'));
        $home->assertOk()
            ->assertSee($live->show->name, false)
            ->assertSee($rehearsal->show->name, false)
            ->assertSee('Set up ticketing', false)
            ->assertSee('href="'.route('studio.performances.ticketing.edit', $live).'"', false)
            ->assertDontSee('href="'.route('studio.performances.ticketing.edit', $rehearsal).'"', false)
            ->assertDontSee('Door / Check-in', false)
            ->assertDontSee('Ticketing on', false);
    }

    public function test_musician_does_not_see_ticketing_actions_on_home_or_the_performance(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $performance = $this->seedPerformance();
        $performance->update([
            'performance_date' => now()->toDateString(),
            'location_name' => 'Grainstore',
        ]);
        $this->saveTicketing($director, $performance, [
            'capacity' => 10,
            'complimentary_allocation' => 0,
            'promotional_allocation' => 0,
        ]);

        $this->actingAs($musician)
            ->get(route('studio'))
            ->assertOk()
            ->assertSee($performance->show->name, false)
            ->assertDontSee('Ticketing on', false)
            ->assertDontSee('Set up ticketing', false)
            ->assertDontSee('Door / Check-in', false)
            ->assertDontSee(route('studio.performances.ticketing.edit', $performance), false)
            ->assertDontSee(route('studio.performances.door', $performance), false);

        $this->actingAs($musician)
            ->get(route('studio.performances.show', $performance))
            ->assertOk()
            ->assertDontSee('Set up ticketing', false)
            ->assertDontSee('Door / Check-in', false)
            ->assertDontSee(route('studio.performances.ticketing.edit', $performance), false)
            ->assertDontSee(route('studio.performances.door', $performance), false);

        $this->actingAs($musician)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertForbidden();
        $this->actingAs($musician)
            ->get(route('studio.performances.door', $performance))
            ->assertForbidden();
    }

    public function test_save_persists_configuration_and_a_reload_shows_the_stored_row(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();

        $response = $this->actingAs($director)
            ->followingRedirects()
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'enabled' => '1',
                'capacity' => 150,
                'currency' => 'NZD',
                'timezone' => 'Pacific/Auckland',
                'sales_open_at' => '2026-10-01T09:00',
                'sales_close_at' => '2026-10-17T18:00',
                'public_sales_enabled' => '1',
                'walk_in_sales_enabled' => '1',
                'interest_registration_enabled' => '1',
                'private_offers_enabled' => '0',
                'marketing_registration_enabled' => '0',
                'offer_validity_minutes' => 720,
                'abandoned_checkout_reminder_minutes' => 90,
                'complimentary_allocation' => 8,
                'promotional_allocation' => 4,
                'tiers' => [[
                    'public_id' => '',
                    'name' => 'Door',
                    'amount' => '10.00',
                    'currency' => 'NZD',
                    'category' => 'General',
                    'starts_at' => '',
                    'ends_at' => '',
                    'enabled' => '1',
                ]],
            ]));

        $response->assertOk()->assertSee('Ticketing settings saved.', false);

        $configuration = PerformanceTicketingConfiguration::query()->where('performance_id', $performance->id)->firstOrFail();
        $this->assertSame(1, PerformanceTicketingConfiguration::query()->where('performance_id', $performance->id)->count());
        $this->assertTrue($configuration->enabled);
        $this->assertSame(150, $configuration->capacity);
        $this->assertSame('NZD', $configuration->currency);
        $this->assertSame('Pacific/Auckland', $configuration->timezone);
        $this->assertSame(720, $configuration->offer_validity_minutes);
        $this->assertSame(90, $configuration->abandoned_checkout_reminder_minutes);
        $this->assertSame(8, $configuration->complimentary_allocation);
        $this->assertSame(4, $configuration->promotional_allocation);
        $this->assertTrue($configuration->public_sales_enabled);
        $this->assertTrue($configuration->walk_in_sales_enabled);
        $this->assertTrue($configuration->interest_registration_enabled);
        $this->assertFalse($configuration->private_offers_enabled);
        $this->assertFalse($configuration->marketing_registration_enabled);

        $tier = TicketPriceTier::query()->where('performance_ticketing_configuration_id', $configuration->id)->firstOrFail();
        $this->assertSame('Door', $tier->name);
        $this->assertSame(1000, $tier->amount_minor);
        $this->assertSame('NZD', $tier->currency);
        $this->assertSame('General', $tier->category);

        $this->actingAs($director)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertOk()
            ->assertSee('value="150"', false)
            ->assertSee('value="NZD"', false)
            ->assertSee('value="Pacific/Auckland" selected', false)
            ->assertSee('value="720"', false)
            ->assertSee('value="90"', false)
            ->assertSee('value="8"', false)
            ->assertSee('value="4"', false)
            ->assertSee('Door', false)
            ->assertSee('10.00', false)
            ->assertSee('General', false)
            ->assertSee('Venue capacity', false)
            ->assertSee('>150<', false);
    }

    public function test_second_save_updates_the_same_row_and_can_turn_booleans_off(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $this->saveTicketing($director, $performance, [
            'capacity' => 150,
            'public_sales_enabled' => '1',
            'walk_in_sales_enabled' => '1',
            'interest_registration_enabled' => '1',
            'marketing_registration_enabled' => '1',
        ]);

        $originalId = $performance->ticketingConfiguration()->firstOrFail()->id;

        $this->actingAs($director)
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'enabled' => '0',
                'capacity' => 120,
                'currency' => 'NZD',
                'public_sales_enabled' => '0',
                'walk_in_sales_enabled' => '0',
                'interest_registration_enabled' => '0',
                'private_offers_enabled' => '0',
                'marketing_registration_enabled' => '0',
                'complimentary_allocation' => 2,
                'promotional_allocation' => 1,
            ]))
            ->assertRedirect(route('studio.performances.ticketing.edit', $performance));

        $this->assertSame(1, PerformanceTicketingConfiguration::query()->count());
        $configuration = $performance->ticketingConfiguration()->firstOrFail();
        $this->assertSame($originalId, $configuration->id);
        $this->assertFalse($configuration->enabled);
        $this->assertSame(120, $configuration->capacity);
        $this->assertFalse($configuration->public_sales_enabled);
        $this->assertFalse($configuration->walk_in_sales_enabled);
        $this->assertFalse($configuration->interest_registration_enabled);
        $this->assertFalse($configuration->marketing_registration_enabled);
        $this->assertSame(2, $configuration->complimentary_allocation);
        $this->assertSame(1, $configuration->promotional_allocation);

        $this->actingAs($director)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertOk()
            ->assertSee('value="120"', false)
            ->assertDontSee('checked', false);
    }

    public function test_empty_price_tier_row_does_not_block_saving_capacity(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();

        $this->actingAs($director)
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'capacity' => 150,
                'public_sales_enabled' => '0',
                'tiers' => [[
                    'public_id' => '',
                    'name' => '',
                    'amount' => '',
                    'currency' => '',
                    'category' => '',
                    'starts_at' => '',
                    'ends_at' => '',
                    'enabled' => '1',
                ]],
            ]))
            ->assertRedirect(route('studio.performances.ticketing.edit', $performance));

        $configuration = $performance->ticketingConfiguration()->firstOrFail();
        $this->assertSame(150, $configuration->capacity);
        $this->assertSame(0, TicketPriceTier::query()->count());
    }

    public function test_validation_failure_does_not_write_a_configuration(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();

        $response = $this->actingAs($director)
            ->from(route('studio.performances.ticketing.edit', $performance))
            ->followingRedirects()
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'capacity' => 150,
                'currency' => '',
                'public_sales_enabled' => '1',
                'tiers' => [[
                    'name' => 'Door',
                    'amount' => '10.00',
                    'currency' => '',
                    'category' => 'General',
                    'enabled' => '1',
                ]],
            ]));

        $response->assertOk()
            ->assertSee('Ticketing was not saved.', false)
            ->assertSee('Each price tier needs a name, amount, and currency.', false)
            ->assertSee('value="150"', false);

        $this->assertSame(0, PerformanceTicketingConfiguration::query()->count());
        $this->assertSame(0, TicketPriceTier::query()->count());
    }

    public function test_musician_cannot_save_ticketing_configuration(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $performance = $this->seedPerformance();

        $this->actingAs($musician)
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload())
            ->assertForbidden();

        $this->assertSame(0, PerformanceTicketingConfiguration::query()->count());
    }

    /**
     * @return list<string>
     */
    private function storedCheckInInstants(int $guestId, int $promoId, int $ticketId): array
    {
        return [
            $this->storedInstant(CheckIn::query()->where('guest_list_entry_id', $guestId)->value('checked_in_at')),
            $this->storedInstant(CheckIn::query()->where('promotional_allocation_id', $promoId)->value('checked_in_at')),
            $this->storedInstant(CheckIn::query()->where('ticket_id', $ticketId)->value('checked_in_at')),
        ];
    }

    private function storedInstant(mixed $value): string
    {
        $this->assertNotNull($value);

        return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }

    private function remaining(Performance $performance): int
    {
        return (int) app(PerformanceCapacityService::class)
            ->snapshot($performance->fresh())['remaining'];
    }

    public function test_each_price_tier_can_be_a_private_offer_independently(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();

        $this->actingAs($director)
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'private_offers_enabled' => '1',
                'tiers' => [
                    [
                        'name' => 'Early Bird',
                        'amount' => '19.00',
                        'currency' => 'NZD',
                        'starts_at' => '2026-09-01T09:00',
                        'ends_at' => '2026-09-30T23:59',
                        'enabled' => '1',
                        'private_offer' => '1',
                    ],
                    [
                        'name' => 'General',
                        'amount' => '35.00',
                        'currency' => 'NZD',
                        'starts_at' => '2026-10-01T00:00',
                        'ends_at' => '2026-10-17T18:00',
                        'enabled' => '1',
                        'private_offer' => '1',
                    ],
                ],
            ]))
            ->assertRedirect(route('studio.performances.ticketing.edit', $performance));

        $configuration = $performance->ticketingConfiguration()->firstOrFail();
        $tiers = $configuration->priceTiers()->orderBy('sort_order')->orderBy('id')->get();
        $this->assertCount(2, $tiers);
        $this->assertTrue($tiers[0]->private_offer);
        $this->assertTrue($tiers[1]->private_offer);
        $this->assertNull($configuration->fresh()->default_offer_price_tier_id);

        $page = $this->actingAs($director)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertOk()
            ->assertSee('Private offer', false)
            ->assertDontSee('type="radio"', false)
            ->assertDontSee('name="default_offer_tier"', false)
            ->assertDontSee('Private offer tier', false);

        $this->assertSame(2, substr_count($page->getContent(), '\u0022private_offer\u0022:true'));

        $unchanged = $this->ticketingPayload([
            'private_offers_enabled' => '1',
            'tiers' => [
                [
                    'public_id' => $tiers[0]->public_id,
                    'name' => 'Early Bird',
                    'amount' => '19.00',
                    'currency' => 'NZD',
                    'starts_at' => '2026-09-01T09:00',
                    'ends_at' => '2026-09-30T23:59',
                    'enabled' => '1',
                    'private_offer' => '1',
                ],
                [
                    'public_id' => $tiers[1]->public_id,
                    'name' => 'General',
                    'amount' => '35.00',
                    'currency' => 'NZD',
                    'starts_at' => '2026-10-01T00:00',
                    'ends_at' => '2026-10-17T18:00',
                    'enabled' => '1',
                    'private_offer' => '1',
                ],
            ],
        ]);

        $this->actingAs($director)
            ->put(route('studio.performances.ticketing.update', $performance), $unchanged)
            ->assertRedirect(route('studio.performances.ticketing.edit', $performance));

        $tiers->each->refresh();
        $this->assertTrue($tiers[0]->private_offer);
        $this->assertTrue($tiers[1]->private_offer);
        $this->assertSame(2, TicketPriceTier::query()->count());

        $this->actingAs($director)
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'private_offers_enabled' => '1',
                'tiers' => [
                    [
                        'public_id' => $tiers[0]->public_id,
                        'name' => 'Early Bird',
                        'amount' => '19.00',
                        'currency' => 'NZD',
                        'starts_at' => '2026-09-01T09:00',
                        'ends_at' => '2026-09-30T23:59',
                        'enabled' => '1',
                        'private_offer' => '0',
                    ],
                    [
                        'public_id' => $tiers[1]->public_id,
                        'name' => 'General',
                        'amount' => '35.00',
                        'currency' => 'NZD',
                        'starts_at' => '2026-10-01T00:00',
                        'ends_at' => '2026-10-17T18:00',
                        'enabled' => '1',
                        'private_offer' => '1',
                    ],
                ],
            ]))
            ->assertRedirect(route('studio.performances.ticketing.edit', $performance));

        $tiers->each->refresh();
        $this->assertFalse($tiers[0]->private_offer);
        $this->assertTrue($tiers[1]->private_offer);
        $this->assertSame($tiers[1]->id, $configuration->fresh()->default_offer_price_tier_id);

        $reloaded = $this->actingAs($director)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertOk();
        $html = $reloaded->getContent();
        $this->assertSame(1, substr_count($html, '\u0022private_offer\u0022:true'));
        $this->assertSame(1, substr_count($html, '\u0022private_offer\u0022:false'));

        $this->actingAs($director)
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'private_offers_enabled' => '1',
                'tiers' => [
                    [
                        'public_id' => $tiers[0]->public_id,
                        'name' => 'Early Bird',
                        'amount' => '19.00',
                        'currency' => 'NZD',
                        'starts_at' => '2026-09-01T09:00',
                        'ends_at' => '2026-09-30T23:59',
                        'enabled' => '1',
                        'private_offer' => '1',
                    ],
                    [
                        'public_id' => $tiers[1]->public_id,
                        'name' => 'General',
                        'amount' => '35.00',
                        'currency' => 'NZD',
                        'starts_at' => '2026-10-01T00:00',
                        'ends_at' => '2026-10-17T18:00',
                        'enabled' => '1',
                        'private_offer' => '0',
                    ],
                ],
            ]))
            ->assertRedirect(route('studio.performances.ticketing.edit', $performance));

        $tiers->each->refresh();
        $this->assertTrue($tiers[0]->private_offer);
        $this->assertFalse($tiers[1]->private_offer);
    }

    public function test_private_offer_choices_survive_a_validation_failure(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();

        $this->actingAs($director)
            ->from(route('studio.performances.ticketing.edit', $performance))
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'private_offers_enabled' => '1',
                'tiers' => [[
                    'name' => 'Early Bird',
                    'amount' => '19.00',
                    'currency' => 'NZD',
                    'enabled' => '1',
                    'private_offer' => '0',
                ]],
            ]))
            ->assertRedirect(route('studio.performances.ticketing.edit', $performance))
            ->assertSessionHasErrors('tiers');

        $this->assertSame(0, PerformanceTicketingConfiguration::query()->count());

        $this->actingAs($director)
            ->from(route('studio.performances.ticketing.edit', $performance))
            ->followingRedirects()
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'sales_open_at' => '2026-10-17T18:00',
                'sales_close_at' => '2026-10-01T09:00',
                'tiers' => [
                    [
                        'name' => 'Early Bird',
                        'amount' => '19.00',
                        'currency' => 'NZD',
                        'enabled' => '1',
                        'private_offer' => '1',
                    ],
                    [
                        'name' => 'General',
                        'amount' => '35.00',
                        'currency' => 'NZD',
                        'enabled' => '1',
                        'private_offer' => '0',
                    ],
                ],
            ]))
            ->assertOk()
            ->assertSee('Ticketing was not saved.', false)
            ->assertSee('Sales must close after they open.', false)
            ->assertSee('\u0022private_offer\u0022:\u00221\u0022', false)
            ->assertSee('\u0022private_offer\u0022:\u00220\u0022', false);

        $this->assertSame(0, PerformanceTicketingConfiguration::query()->count());
    }

    public function test_a_disabled_tier_cannot_be_marked_as_a_private_offer(): void
    {
        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();

        $this->actingAs($director)
            ->from(route('studio.performances.ticketing.edit', $performance))
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload([
                'private_offers_enabled' => '1',
                'tiers' => [[
                    'name' => 'Early Bird',
                    'amount' => '19.00',
                    'currency' => 'NZD',
                    'enabled' => '0',
                    'private_offer' => '1',
                ]],
            ]))
            ->assertRedirect(route('studio.performances.ticketing.edit', $performance))
            ->assertSessionHasErrors('tiers');

        $this->assertSame(0, PerformanceTicketingConfiguration::query()->count());
    }

    public function test_ticketing_page_shows_the_meta_offer_link_for_this_gig(): void
    {
        config([
            'ticketing.public_base_url' => 'https://edandtheshadowboys.com',
            'ticketing.campaign_key' => config('app.key'),
        ]);
        require_once dirname(base_path(), 2).'/edandtheshadows/app/Services/Ticketing/PrivateOfferCampaign.php';

        $director = $this->createDirectorUser();
        $performance = $this->seedPerformance();
        $other = $this->seedPerformance();
        $url = app(PrivateOfferLink::class)->url($performance);

        $this->assertSame(
            '/performances/'.$performance->public_id,
            parse_url($url, PHP_URL_PATH),
        );
        $this->assertNotSame((string) $performance->id, $performance->public_id);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('meta', $query['campaign']);
        $this->assertSame('meta', $query['source']);
        $this->assertTrue(app(PrivateOfferCampaign::class)->isValid(
            $performance,
            'meta',
            'meta',
            $query['entry'],
        ));
        $this->assertFalse(app(PrivateOfferCampaign::class)->isValid(
            $other,
            'meta',
            'meta',
            $query['entry'],
        ));
        $this->assertStringNotContainsString('/t/', $url);

        $this->actingAs($director)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertOk()
            ->assertSee('Private offer link', false)
            ->assertSee('Copy link', false)
            ->assertSee($performance->public_id, false)
            ->assertSee('campaign=meta', false)
            ->assertDontSee('/t/', false);

        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $this->actingAs($musician)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertForbidden();
    }

    private function saveTicketing(User $director, Performance $performance, array $overrides = []): void
    {
        $this->actingAs($director)
            ->get(route('studio.performances.ticketing.edit', $performance))
            ->assertOk();

        $this->actingAs($director)
            ->put(route('studio.performances.ticketing.update', $performance), $this->ticketingPayload($overrides))
            ->assertRedirect(route('studio.performances.ticketing.edit', $performance));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ticketingPayload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => '1',
            'capacity' => 100,
            'currency' => 'NZD',
            'timezone' => 'Pacific/Auckland',
            'offer_validity_minutes' => 1440,
            'abandoned_checkout_reminder_minutes' => 240,
            'complimentary_allocation' => 10,
            'promotional_allocation' => 5,
            'public_sales_enabled' => '0',
            'walk_in_sales_enabled' => '0',
            'interest_registration_enabled' => '0',
            'private_offers_enabled' => '0',
            'marketing_registration_enabled' => '0',
            'tiers' => [[
                'name' => 'General admission',
                'amount' => '19.50',
                'currency' => 'NZD',
                'enabled' => '1',
            ]],
        ], $overrides);
    }

    private function seedPerformance(): Performance
    {
        $show = app(StudioShowService::class)->createShow([
            'name' => 'Show '.uniqid(),
            'lifecycle_state' => Show::STATE_DRAFT,
        ]);

        return app(StudioPerformanceService::class)->createPerformance([
            'show_id' => $show->id,
            'performance_type' => Performance::TYPE_LIVE,
            'status' => Performance::STATUS_CONFIRMED,
            'location_name' => 'Venue',
            'performance_date' => '2026-10-17',
        ]);
    }
}
