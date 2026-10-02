@extends('layouts.portal')

@section('title', ($performance->eventContextLabel() ?: 'Door').' — Door — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        @include('studio.partials._chrome-header', [
            'pageTitle' => $performance->eventContextLabel() ?: 'Door',
            'pageLead' => 'Door / Check-in',
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('studio')],
                ['label' => 'Schedule', 'url' => route('studio.calendar.index')],
                ['label' => $performance->show?->name ?? 'Performance', 'url' => route('studio.performances.show', $performance)],
                ['label' => 'Door'],
            ],
        ])

        <div class="esb-studio__shell-body">
            @include('studio.performances.partials._ticketing-nav', [
                'performance' => $performance,
                'showTicketing' => true,
            ])

            @if (session('door_success'))
                <p class="esb-portal__success mb-4" role="status">{{ session('door_success') }}</p>
            @endif
            @if (session('door_notice'))
                <p class="esb-portal__error mb-4" role="status">{{ session('door_notice') }}</p>
            @endif
            @if (session('door_error'))
                <p class="esb-portal__error mb-4" role="alert">{{ session('door_error') }}</p>
            @endif

            <form id="door-check-in" class="esb-portal__panel esb-studio__card mt-4" method="POST" action="{{ route('studio.performances.door.check-in', $performance) }}">
                @csrf
                <label class="esb-portal__label mb-2 block" for="door-token">Ticket code</label>
                <div class="esb-studio__door-entry">
                    <button type="button" id="door-scan-start" class="esb-portal__button esb-portal__button--secondary" aria-controls="door-scan-panel" aria-expanded="false">Scan QR</button>
                    <input id="door-token" name="token" type="text" class="esb-portal__input" autofocus autocomplete="off" placeholder="Scan or paste the ticket code">
                    <button type="submit" class="esb-portal__button esb-portal__button--primary">Check in</button>
                </div>
                <p id="door-scan-status" class="esb-portal__error mt-3" role="status" hidden></p>
                <div id="door-scan-panel" class="esb-studio__door-preview" hidden>
                    <video id="door-scan-video" class="esb-studio__door-video" playsinline muted autoplay aria-label="Ticket QR camera preview"></video>
                    <button type="button" id="door-scan-cancel" class="esb-portal__button esb-portal__button--secondary mt-3">Cancel scan</button>
                </div>
            </form>

            <form class="mt-4" method="GET" action="{{ route('studio.performances.door', $performance) }}">
                <label class="esb-portal__label mb-2 block" for="door-search">Search</label>
                <input id="door-search" name="q" type="search" class="esb-portal__input" value="{{ $query }}" placeholder="Name, email, or ticket code">
                <button type="submit" class="esb-portal__button esb-portal__button--secondary mt-3">Search</button>
            </form>

            @if ($performance->ticketingConfiguration?->timezone)
                <p class="esb-studio__field-hint mt-4">Arrival times are shown in {{ $performance->ticketingConfiguration->timezone }}.</p>
            @endif

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Tickets</h2>
                <ul class="esb-studio__availability-list mt-4">
                    @forelse ($tickets as $ticket)
                        <li class="esb-studio__availability-item">
                            <span class="esb-studio__availability-name">{{ $ticket->attendee_name ?: 'Ticket' }}</span>
                            <span class="esb-studio__availability-status">
                                @if ($ticket->checkIn)
                                    Already checked in · {{ $performance->ticketingConfiguration?->formatInstant($ticket->checkIn->checked_in_at) }}
                                @else
                                    Not arrived
                                @endif
                            </span>
                            <span class="esb-studio__availability-notes">Ticket code</span>
                            <span class="esb-studio__door-ticket-code">{{ $ticket->public_id }}</span>
                            @unless ($ticket->checkIn)
                                <form method="POST" action="{{ route('studio.performances.door.check-in', $performance) }}">
                                    @csrf
                                    <input type="hidden" name="token" value="{{ $ticket->public_id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="esb-portal__button esb-portal__button--primary">Check in</button>
                                </form>
                            @endunless
                        </li>
                    @empty
                        <li class="esb-studio__card-body">No matching tickets.</li>
                    @endforelse
                </ul>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Guest list</h2>
                <ul class="esb-studio__availability-list mt-4">
                    @forelse ($guests as $guest)
                        <li class="esb-studio__availability-item">
                            <span class="esb-studio__availability-name">{{ $guest->guest_name }}</span>
                            <span class="esb-studio__availability-status">Allocated {{ $guest->quantity }} · checked in {{ $guest->checkedInQuantity() }} · not yet arrived {{ $guest->quantity - $guest->checkedInQuantity() }}</span>
                            @if ($guest->checkedInQuantity() >= $guest->quantity)
                                <span class="esb-studio__availability-notes">Already checked in · {{ $performance->ticketingConfiguration?->formatInstant($guest->checkIns->sortBy('checked_in_at')->first()?->checked_in_at) }}</span>
                            @else
                                <form method="POST" action="{{ route('studio.performances.door.check-in', $performance) }}">
                                    @csrf
                                    <input type="hidden" name="token" value="{{ $guest->public_id }}">
                                    <label class="esb-portal__label mb-2 block" for="guest-arrive-{{ $guest->id }}">Arriving now</label>
                                    <input id="guest-arrive-{{ $guest->id }}" name="quantity" type="number" min="1" max="{{ $guest->quantity - $guest->checkedInQuantity() }}" value="1" class="esb-portal__input">
                                    <button type="submit" class="esb-portal__button esb-portal__button--primary mt-3">Check in</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="esb-studio__card-body">No matching guests.</li>
                    @endforelse
                </ul>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Claimed promotional tickets</h2>
                <ul class="esb-studio__availability-list mt-4">
                    @forelse ($promos as $promo)
                        <li class="esb-studio__availability-item">
                            <span class="esb-studio__availability-name">{{ $promo->holder_name ?: ($promo->label ?: 'Promotional ticket') }}</span>
                            <span class="esb-studio__availability-status">Allocated {{ $promo->quantity }} · checked in {{ $promo->checkedInQuantity() }} · not yet arrived {{ $promo->quantity - $promo->checkedInQuantity() }}</span>
                            @if ($promo->checkedInQuantity() >= $promo->quantity)
                                <span class="esb-studio__availability-notes">Already checked in · {{ $performance->ticketingConfiguration?->formatInstant($promo->checkIns->sortBy('checked_in_at')->first()?->checked_in_at) }}</span>
                            @else
                                <form method="POST" action="{{ route('studio.performances.door.check-in', $performance) }}">
                                    @csrf
                                    <input type="hidden" name="token" value="{{ $promo->public_id }}">
                                    <label class="esb-portal__label mb-2 block" for="promo-arrive-{{ $promo->id }}">Arriving now</label>
                                    <input id="promo-arrive-{{ $promo->id }}" name="quantity" type="number" min="1" max="{{ $promo->quantity - $promo->checkedInQuantity() }}" value="1" class="esb-portal__input">
                                    <button type="submit" class="esb-portal__button esb-portal__button--primary mt-3">Check in</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="esb-studio__card-body">No claimed promotional tickets.</li>
                    @endforelse
                </ul>
            </section>

            @include('studio.performances.partials._ticketing-capacity', ['capacity' => $capacity])
        </div>

        <footer class="esb-studio__chrome-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="esb-portal__button esb-portal__button--secondary">Log out</button>
            </form>
        </footer>
    </main>
@endsection

@push('scripts')
    @php
        $doorScannerReady = file_exists(public_path('hot'));

        if (! $doorScannerReady && file_exists(public_path('build/manifest.json'))) {
            $doorScannerManifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
            $doorScannerReady = is_array($doorScannerManifest) && isset($doorScannerManifest['resources/js/studio-door-scan.js']);
        }
    @endphp
    @if ($doorScannerReady)
        @vite(['resources/js/studio-door-scan.js'])
    @endif
@endpush
