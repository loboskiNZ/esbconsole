<?php

namespace App\Http\Controllers;

use App\Exceptions\PerformanceTicketingException;
use App\Http\Requests\StoreGuestListEntryRequest;
use App\Http\Requests\StoreManualAdmissionRequest;
use App\Http\Requests\StorePromotionalAllocationRequest;
use App\Http\Requests\UpdatePerformanceTicketingRequest;
use App\Models\GuestListEntry;
use App\Models\Performance;
use App\Models\PerformanceTicketingConfiguration;
use App\Models\PromotionalAllocation;
use App\Models\PurchaseOffer;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPriceTier;
use App\Services\PerformanceAttendanceService;
use App\Services\PerformanceCapacityService;
use App\Services\PerformanceTicketingService;
use App\Services\StudioPerformanceService;
use App\Support\Iso4217;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudioPerformanceTicketingController extends Controller
{
    public function edit(
        Performance $performance,
        StudioPerformanceService $performances,
        PerformanceCapacityService $capacity,
    ): View {
        $portalPerformance = $performances->performanceForPortal($performance->id);
        $configuration = $portalPerformance->ticketingConfiguration()->with('priceTiers')->first();

        return view('studio.performances.ticketing', [
            'performance' => $portalPerformance,
            'configuration' => $configuration,
            'tierRows' => $this->tierRows($configuration),
            'capacity' => $capacity->snapshot($portalPerformance),
            'guests' => GuestListEntry::query()->where('performance_id', $portalPerformance->id)->with('checkIns')->orderBy('guest_name')->get(),
            'promos' => PromotionalAllocation::query()->where('performance_id', $portalPerformance->id)->with('checkIns')->orderBy('id')->get(),
            'orders' => TicketOrder::query()->where('performance_id', $portalPerformance->id)->with(['tickets.checkIn'])->orderByDesc('id')->get(),
            'currencies' => Iso4217::codes(),
            'timezones' => timezone_identifiers_list(),
            'promoStatuses' => PromotionalAllocation::statuses(),
            'purchaseOffers' => PurchaseOffer::query()
                ->where('performance_id', $portalPerformance->id)
                ->orderByDesc('id')
                ->get(['slug']),
        ]);
    }

    public function update(
        UpdatePerformanceTicketingRequest $request,
        Performance $performance,
        StudioPerformanceService $performances,
        PerformanceTicketingService $ticketing,
    ): RedirectResponse {
        $portalPerformance = $performances->performanceForPortal($performance->id);

        try {
            $ticketing->save($portalPerformance, $request->validatedPayload(), $request->user());
        } catch (PerformanceTicketingException $exception) {
            return back()->withInput()->with('ticketing_error', $exception->getMessage());
        } catch (\Throwable $exception) {
            try {
                report($exception);
            } catch (\Throwable) {
                // A logging failure must not replace the message the director sees.
            }

            return back()->withInput()->with('ticketing_error', 'Ticketing could not be saved. Nothing was stored.');
        }

        return redirect()
            ->route('studio.performances.ticketing.edit', $portalPerformance)
            ->with('ticketing_saved', true);
    }

    public function storeGuest(
        StoreGuestListEntryRequest $request,
        Performance $performance,
        StudioPerformanceService $performances,
        PerformanceAttendanceService $attendance,
    ): RedirectResponse {
        $portalPerformance = $performances->performanceForPortal($performance->id);

        try {
            $attendance->addGuest($portalPerformance, $request->validatedPayload(), $request->user());
        } catch (PerformanceTicketingException $exception) {
            return back()->with('ticketing_error', $exception->getMessage());
        }

        return back()->with('guest_saved', true);
    }

    public function destroyGuest(
        Request $request,
        Performance $performance,
        GuestListEntry $guestListEntry,
        StudioPerformanceService $performances,
        PerformanceAttendanceService $attendance,
    ): RedirectResponse {
        $portalPerformance = $performances->performanceForPortal($performance->id);

        try {
            $attendance->removeGuest($portalPerformance, $guestListEntry, $request->user());
        } catch (PerformanceTicketingException $exception) {
            return back()->with('ticketing_error', $exception->getMessage());
        }

        return back()->with('guest_removed', true);
    }

    public function storePromo(
        StorePromotionalAllocationRequest $request,
        Performance $performance,
        StudioPerformanceService $performances,
        PerformanceAttendanceService $attendance,
    ): RedirectResponse {
        $portalPerformance = $performances->performanceForPortal($performance->id);

        try {
            $attendance->addPromo($portalPerformance, $request->validatedPayload(), $request->user());
        } catch (PerformanceTicketingException $exception) {
            return back()->with('ticketing_error', $exception->getMessage());
        }

        return back()->with('promo_saved', true);
    }

    public function updatePromo(
        Request $request,
        Performance $performance,
        PromotionalAllocation $promotionalAllocation,
        StudioPerformanceService $performances,
        PerformanceAttendanceService $attendance,
    ): RedirectResponse {
        $portalPerformance = $performances->performanceForPortal($performance->id);
        $status = (string) $request->input('status', '');

        try {
            $attendance->updatePromo($portalPerformance, $promotionalAllocation, $status, $request->user());
        } catch (PerformanceTicketingException $exception) {
            return back()->with('ticketing_error', $exception->getMessage());
        }

        return back()->with('promo_saved', true);
    }

    public function storeManualAdmission(
        StoreManualAdmissionRequest $request,
        Performance $performance,
        StudioPerformanceService $performances,
        PerformanceAttendanceService $attendance,
    ): RedirectResponse {
        $portalPerformance = $performances->performanceForPortal($performance->id);

        try {
            $attendance->createManualAdmission($portalPerformance, $request->validatedPayload(), $request->user());
        } catch (PerformanceTicketingException $exception) {
            return back()->with('ticketing_error', $exception->getMessage());
        }

        return back()->with('manual_admission_saved', true);
    }

    public function door(
        Request $request,
        Performance $performance,
        StudioPerformanceService $performances,
        PerformanceCapacityService $capacity,
    ): View {
        $portalPerformance = $performances->performanceForPortal($performance->id);
        $portalPerformance->loadMissing('ticketingConfiguration');
        $query = trim((string) $request->query('q', ''));

        $guests = GuestListEntry::query()
            ->where('performance_id', $portalPerformance->id)
            ->with('checkIns')
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('guest_name', 'like', '%'.$query.'%')
                        ->orWhere('email', 'like', '%'.$query.'%');
                });
            })
            ->orderBy('guest_name')
            ->get();

        $promos = PromotionalAllocation::query()
            ->where('performance_id', $portalPerformance->id)
            ->where('status', PromotionalAllocation::STATUS_CLAIMED)
            ->with('checkIns')
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('holder_name', 'like', '%'.$query.'%')
                        ->orWhere('label', 'like', '%'.$query.'%')
                        ->orWhere('holder_email', 'like', '%'.$query.'%');
                });
            })
            ->orderBy('holder_name')
            ->get();

        return view('studio.performances.door', [
            'performance' => $portalPerformance,
            'capacity' => $capacity->snapshot($portalPerformance),
            'guests' => $guests,
            'promos' => $promos,
            'tickets' => Ticket::query()
                ->where('performance_id', $portalPerformance->id)
                ->where('status', Ticket::STATUS_VALID)
                ->whereHas('order', fn ($order) => $order->where('status', TicketOrder::STATUS_CONFIRMED))
                ->with('checkIn')
                ->when($query !== '', function ($builder) use ($query): void {
                    $builder->where(function ($inner) use ($query): void {
                        $inner->where('attendee_name', 'like', '%'.$query.'%')
                            ->orWhere('attendee_email', 'like', '%'.$query.'%')
                            ->orWhere('public_id', $query);
                    });
                })
                ->orderBy('attendee_name')
                ->get(),
            'query' => $query,
        ]);
    }

    public function checkIn(
        Request $request,
        Performance $performance,
        StudioPerformanceService $performances,
        PerformanceAttendanceService $attendance,
    ): RedirectResponse {
        $portalPerformance = $performances->performanceForPortal($performance->id);
        $portalPerformance->loadMissing('ticketingConfiguration');

        try {
            $result = $attendance->checkInToken(
                $portalPerformance,
                (string) $request->input('token', ''),
                (int) $request->input('quantity', 1),
                $request->user(),
            );
        } catch (PerformanceTicketingException $exception) {
            return back()->with('door_error', $exception->getMessage());
        }

        if ($result['state'] === 'already') {
            $when = $portalPerformance->ticketingConfiguration?->formatInstant($result['checked_in_at']) ?? '';

            return back()->with('door_notice', 'Already checked in'.($when !== '' ? ' · '.$when : '').' · '.$result['label']);
        }

        $detail = $result['checked_in_quantity'].' of '.$result['allocated'].' arrived';
        if ($result['not_yet_arrived'] > 0) {
            $detail .= ' · '.$result['not_yet_arrived'].' still to arrive';
        }

        return back()->with('door_success', 'Checked in · '.$result['label'].' · '.$detail);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tierRows(?PerformanceTicketingConfiguration $configuration): array
    {
        $old = old('tiers');
        if (is_array($old)) {
            return array_values($old);
        }

        if ($configuration === null || $configuration->priceTiers->isEmpty()) {
            return [[
                'public_id' => '',
                'name' => '',
                'amount' => '',
                'currency' => '',
                'category' => '',
                'starts_at' => '',
                'ends_at' => '',
                'enabled' => '1',
                'private_offer' => false,
            ]];
        }

        return $configuration->priceTiers->map(function (TicketPriceTier $tier) use ($configuration): array {
            return [
                'public_id' => $tier->public_id,
                'name' => $tier->name,
                'amount' => $tier->amountDecimal(),
                'currency' => $tier->currency,
                'category' => $tier->category ?? '',
                'starts_at' => $configuration->localInput($tier->starts_at),
                'ends_at' => $configuration->localInput($tier->ends_at),
                'enabled' => $tier->enabled ? '1' : '0',
                'private_offer' => (bool) $tier->private_offer,
            ];
        })->all();
    }
}
