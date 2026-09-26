@extends('layouts.portal')

@section('title', ($performance->eventContextLabel() ?: 'Ticketing').' — Ticketing — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        @include('studio.partials._chrome-header', [
            'pageTitle' => $performance->eventContextLabel() ?: 'Ticketing',
            'pageLead' => 'Ticketing',
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('studio')],
                ['label' => 'Schedule', 'url' => route('studio.calendar.index')],
                ['label' => $performance->show?->name ?? 'Performance', 'url' => route('studio.performances.show', $performance)],
                ['label' => 'Ticketing'],
            ],
        ])

        <div class="esb-studio__shell-body">
            @include('studio.performances.partials._ticketing-nav', [
                'performance' => $performance,
                'showDoor' => (bool) $configuration?->enabled,
            ])

            @if (session('ticketing_saved'))
                <p class="esb-portal__success mb-4" role="status">Ticketing settings saved.</p>
            @endif
            @if (session('guest_saved'))
                <p class="esb-portal__success mb-4" role="status">Guest added.</p>
            @endif
            @if (session('guest_removed'))
                <p class="esb-portal__success mb-4" role="status">Guest removed.</p>
            @endif
            @if (session('promo_saved'))
                <p class="esb-portal__success mb-4" role="status">Promotional tickets updated.</p>
            @endif
            @if (session('manual_admission_saved'))
                <p class="esb-portal__success mb-4" role="status">Manual admission created. This is not a payment.</p>
            @endif
            @if (session('ticketing_error'))
                <div class="esb-portal__error mb-4" role="alert">
                    <p>Ticketing was not saved.</p>
                    <p>{{ session('ticketing_error') }}</p>
                </div>
            @endif
            @if ($errors->any())
                <div class="esb-portal__error mb-4" role="alert">
                    <p>Ticketing was not saved.</p>
                    <ul class="esb-studio__users-error-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('studio.performances.partials._ticketing-capacity', ['capacity' => $capacity])

            <form
                class="esb-portal__panel esb-studio__card esb-studio__performance-form mt-4"
                method="POST"
                action="{{ route('studio.performances.ticketing.update', $performance) }}"
            >
                @csrf
                @method('PUT')

                <section class="esb-studio__band-section">
                    <h2 class="esb-studio__band-section-title">General</h2>
                    <div class="esb-studio__band-form-grid">
                        <label class="esb-studio__users-role-option">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $configuration?->enabled) == true)>
                            <span>Ticketing enabled</span>
                        </label>

                        <div>
                            <label class="esb-portal__label mb-2 block" for="ticketing-capacity">Venue capacity</label>
                            <input id="ticketing-capacity" name="capacity" type="number" min="0" class="esb-portal__input" value="{{ old('capacity', $configuration?->capacity) }}">
                            @error('capacity')
                                <p class="esb-portal__error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="esb-portal__label mb-2 block" for="ticketing-currency">Currency</label>
                            <input id="ticketing-currency" name="currency" type="text" maxlength="3" class="esb-portal__input" list="ticketing-currencies" value="{{ old('currency', $configuration?->currency) }}" autocapitalize="characters">
                            <datalist id="ticketing-currencies">
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency }}"></option>
                                @endforeach
                            </datalist>
                            <p class="esb-studio__field-hint mt-1">ISO 4217 code for new prices. Existing tiers and sales keep the currency they were given.</p>
                            @error('currency')
                                <p class="esb-portal__error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="esb-portal__label mb-2 block" for="ticketing-timezone">Timezone</label>
                            <select id="ticketing-timezone" name="timezone" class="esb-portal__input" required>
                                @foreach ($timezones as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('timezone', $configuration?->timezone ?? 'UTC') === $timezone)>{{ $timezone }}</option>
                                @endforeach
                            </select>
                            <p class="esb-studio__field-hint mt-1">Sales times are entered in this timezone and stored in UTC. Check-in times are shown in this timezone.</p>
                            @error('timezone')
                                <p class="esb-portal__error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="esb-portal__label mb-2 block" for="ticketing-sales-open">Sales open</label>
                            <input id="ticketing-sales-open" name="sales_open_at" type="datetime-local" class="esb-portal__input" value="{{ old('sales_open_at', $configuration ? $configuration->localInput($configuration->sales_open_at) : '') }}">
                            @error('sales_open_at')
                                <p class="esb-portal__error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="esb-portal__label mb-2 block" for="ticketing-sales-close">Sales close</label>
                            <input id="ticketing-sales-close" name="sales_close_at" type="datetime-local" class="esb-portal__input" value="{{ old('sales_close_at', $configuration ? $configuration->localInput($configuration->sales_close_at) : '') }}">
                            @error('sales_close_at')
                                <p class="esb-portal__error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="esb-studio__users-role-option">
                            <input type="hidden" name="public_sales_enabled" value="0">
                            <input type="checkbox" name="public_sales_enabled" value="1" @checked(old('public_sales_enabled', $configuration?->public_sales_enabled) == true)>
                            <span>Public ticket sales</span>
                        </label>
                        <label class="esb-studio__users-role-option">
                            <input type="hidden" name="walk_in_sales_enabled" value="0">
                            <input type="checkbox" name="walk_in_sales_enabled" value="1" @checked(old('walk_in_sales_enabled', $configuration?->walk_in_sales_enabled) == true)>
                            <span>Walk-in sales</span>
                        </label>
                        <label class="esb-studio__users-role-option">
                            <input type="hidden" name="interest_registration_enabled" value="0">
                            <input type="checkbox" name="interest_registration_enabled" value="1" @checked(old('interest_registration_enabled', $configuration?->interest_registration_enabled) == true)>
                            <span>Interest registration</span>
                        </label>
                        <label class="esb-studio__users-role-option">
                            <input type="hidden" name="private_offers_enabled" value="0">
                            <input type="checkbox" name="private_offers_enabled" value="1" @checked(old('private_offers_enabled', $configuration?->private_offers_enabled) == true)>
                            <span>Private offers</span>
                        </label>
                        <label class="esb-studio__users-role-option">
                            <input type="hidden" name="marketing_registration_enabled" value="0">
                            <input type="checkbox" name="marketing_registration_enabled" value="1" @checked(old('marketing_registration_enabled', $configuration?->marketing_registration_enabled) == true)>
                            <span>Marketing registration</span>
                        </label>

                        <div>
                            <label class="esb-portal__label mb-2 block" for="ticketing-offer-minutes">Private offer validity (minutes)</label>
                            <input id="ticketing-offer-minutes" name="offer_validity_minutes" type="number" min="1" class="esb-portal__input" value="{{ old('offer_validity_minutes', $configuration->offer_validity_minutes ?? 1440) }}" required>
                            <p class="esb-studio__field-hint mt-1">Elapsed time from when an offer is created. 1440 minutes is 24 hours.</p>
                        </div>

                        <div>
                            <label class="esb-portal__label mb-2 block" for="ticketing-reminder-minutes">Abandoned checkout reminder (minutes)</label>
                            <input id="ticketing-reminder-minutes" name="abandoned_checkout_reminder_minutes" type="number" min="1" class="esb-portal__input" value="{{ old('abandoned_checkout_reminder_minutes', $configuration->abandoned_checkout_reminder_minutes ?? 240) }}" required>
                        </div>

                        <div>
                            <label class="esb-portal__label mb-2 block" for="ticketing-comp-allocation">Complimentary / guest allocation</label>
                            <input id="ticketing-comp-allocation" name="complimentary_allocation" type="number" min="0" class="esb-portal__input" value="{{ old('complimentary_allocation', $configuration->complimentary_allocation ?? 0) }}" required>
                        </div>

                        <div>
                            <label class="esb-portal__label mb-2 block" for="ticketing-promo-allocation">Promotional allocation</label>
                            <input id="ticketing-promo-allocation" name="promotional_allocation" type="number" min="0" class="esb-portal__input" value="{{ old('promotional_allocation', $configuration->promotional_allocation ?? 0) }}" required>
                        </div>
                    </div>
                </section>

                <section
                    class="esb-studio__band-section"
                    x-data="studioTicketing(@js($tierRows))"
                >
                    <h2 class="esb-studio__band-section-title">Price tiers</h2>
                    <p class="esb-studio__field-hint">Each tier keeps its own amount and currency. Mark every tier that can be sent as its own private-offer link. Tiers with different dates stay separate choices.</p>
                    @error('tiers')
                        <p class="esb-portal__error mt-2">{{ $message }}</p>
                    @enderror

                    <template x-for="(tier, index) in tiers" :key="index">
                        <div class="esb-studio__ticketing-tier mt-4">
                            <input type="hidden" :name="`tiers[${index}][public_id]`" x-model="tier.public_id">
                            <div class="esb-studio__ticketing-tier-grid">
                                <div>
                                    <label class="esb-portal__label mb-2 block">Name</label>
                                    <input class="esb-portal__input" type="text" :name="`tiers[${index}][name]`" x-model="tier.name">
                                </div>
                                <div>
                                    <label class="esb-portal__label mb-2 block">Amount</label>
                                    <input class="esb-portal__input" type="text" inputmode="decimal" :name="`tiers[${index}][amount]`" x-model="tier.amount">
                                </div>
                                <div>
                                    <label class="esb-portal__label mb-2 block">Currency</label>
                                    <input class="esb-portal__input" type="text" maxlength="3" :name="`tiers[${index}][currency]`" x-model="tier.currency">
                                </div>
                                <div>
                                    <label class="esb-portal__label mb-2 block">Category</label>
                                    <input class="esb-portal__input" type="text" :name="`tiers[${index}][category]`" x-model="tier.category">
                                </div>
                                <div>
                                    <label class="esb-portal__label mb-2 block">Starts</label>
                                    <input class="esb-portal__input" type="datetime-local" :name="`tiers[${index}][starts_at]`" x-model="tier.starts_at">
                                </div>
                                <div>
                                    <label class="esb-portal__label mb-2 block">Ends</label>
                                    <input class="esb-portal__input" type="datetime-local" :name="`tiers[${index}][ends_at]`" x-model="tier.ends_at">
                                </div>
                                <div>
                                    <label class="esb-portal__label mb-2 block">Enabled</label>
                                    <select class="esb-portal__input" :name="`tiers[${index}][enabled]`" x-model="tier.enabled">
                                        <option value="1">Enabled</option>
                                        <option value="0">Disabled</option>
                                    </select>
                                </div>
                                <div class="esb-studio__ticketing-tier-actions">
                                    <label class="esb-studio__users-role-option">
                                        <input type="hidden" :name="`tiers[${index}][private_offer]`" value="0">
                                        <input type="checkbox" value="1" :name="`tiers[${index}][private_offer]`" x-model="tier.private_offer">
                                        <span>Private offer</span>
                                    </label>
                                    <button type="button" class="esb-portal__button esb-portal__button--secondary" @click="removeTier(index)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="button" class="esb-portal__button esb-portal__button--secondary mt-4" @click="addTier()">Add price tier</button>
                </section>

                <div class="esb-studio__band-form-actions mt-6">
                    <button type="submit" class="esb-portal__button esb-portal__button--primary">Save ticketing</button>
                </div>
            </form>

            @if ($configuration?->enabled)
                <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                    <h2 class="esb-studio__card-title">Guest list</h2>
                    <form class="esb-studio__band-form-grid mt-4" method="POST" action="{{ route('studio.performances.guests.store', $performance) }}">
                        @csrf
                        <div>
                            <label class="esb-portal__label mb-2 block" for="guest-name">Guest name</label>
                            <input id="guest-name" name="guest_name" type="text" class="esb-portal__input" required>
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="guest-email">Email</label>
                            <input id="guest-email" name="email" type="email" class="esb-portal__input">
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="guest-quantity">Quantity</label>
                            <input id="guest-quantity" name="quantity" type="number" min="1" value="1" class="esb-portal__input" required>
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="guest-category">Category</label>
                            <input id="guest-category" name="category" type="text" class="esb-portal__input">
                            <p class="esb-studio__field-hint mt-1">A label for this performance, such as the band, a guest artist, festival, or media.</p>
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="guest-notes">Notes</label>
                            <textarea id="guest-notes" name="notes" rows="2" class="esb-portal__input esb-studio__band-textarea"></textarea>
                        </div>
                        <div>
                            <button type="submit" class="esb-portal__button esb-portal__button--primary">Add guest</button>
                        </div>
                    </form>

                    <ul class="esb-studio__availability-list mt-4">
                        @forelse ($guests as $guest)
                            <li class="esb-studio__availability-item">
                                <span class="esb-studio__availability-name">{{ $guest->guest_name }} · allocated {{ $guest->quantity }} · checked in {{ $guest->checkedInQuantity() }} · not yet arrived {{ $guest->quantity - $guest->checkedInQuantity() }}</span>
                                <span class="esb-studio__availability-status">{{ $guest->category ?: 'Guest' }}</span>
                                @if ($guest->email)
                                    <span class="esb-studio__availability-notes">{{ $guest->email }}</span>
                                @endif
                                @unless ($guest->checkedInQuantity() > 0)
                                    <form method="POST" action="{{ route('studio.performances.guests.destroy', [$performance, $guest]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="esb-portal__button esb-portal__button--secondary">Remove</button>
                                    </form>
                                @endunless
                            </li>
                        @empty
                            <li class="esb-studio__card-body">No guests yet.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                    <h2 class="esb-studio__card-title">Promotional tickets</h2>
                    <form class="esb-studio__band-form-grid mt-4" method="POST" action="{{ route('studio.performances.promos.store', $performance) }}">
                        @csrf
                        <div>
                            <label class="esb-portal__label mb-2 block" for="promo-label">Label</label>
                            <input id="promo-label" name="label" type="text" class="esb-portal__input">
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="promo-holder">Holder name</label>
                            <input id="promo-holder" name="holder_name" type="text" class="esb-portal__input">
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="promo-quantity">Quantity</label>
                            <input id="promo-quantity" name="quantity" type="number" min="1" value="1" class="esb-portal__input" required>
                        </div>
                        <div>
                            <button type="submit" class="esb-portal__button esb-portal__button--primary">Add promotional ticket</button>
                        </div>
                    </form>

                    <ul class="esb-studio__availability-list mt-4">
                        @forelse ($promos as $promo)
                            <li class="esb-studio__availability-item">
                                <span class="esb-studio__availability-name">{{ $promo->holder_name ?: ($promo->label ?: 'Promotional ticket') }} · allocated {{ $promo->quantity }} · checked in {{ $promo->checkedInQuantity() }} · not yet arrived {{ $promo->quantity - $promo->checkedInQuantity() }}</span>
                                <form method="POST" action="{{ route('studio.performances.promos.update', [$performance, $promo]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="esb-portal__label mb-2 block" for="promo-status-{{ $promo->id }}">Status</label>
                                    <select id="promo-status-{{ $promo->id }}" name="status" class="esb-portal__input">
                                        @foreach ($promoStatuses as $status)
                                            <option value="{{ $status }}" @selected($promo->status === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="esb-portal__button esb-portal__button--secondary mt-2">Update</button>
                                </form>
                            </li>
                        @empty
                            <li class="esb-studio__card-body">No promotional tickets yet.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                    <h2 class="esb-studio__card-title">Orders and tickets</h2>
                    <p class="esb-studio__field-hint mt-3">A manual admission confirms entry without taking payment. The ticket is not checked in until the door records it.</p>
                    <form class="esb-studio__band-form-grid mt-4" method="POST" action="{{ route('studio.performances.manual-admissions.store', $performance) }}">
                        @csrf
                        <div>
                            <label class="esb-portal__label mb-2 block" for="manual-name">Attendee name</label>
                            <input id="manual-name" name="attendee_name" type="text" class="esb-portal__input" required>
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="manual-email">Email</label>
                            <input id="manual-email" name="attendee_email" type="email" class="esb-portal__input">
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="manual-quantity">Tickets</label>
                            <input id="manual-quantity" name="quantity" type="number" min="1" value="1" class="esb-portal__input" required>
                        </div>
                        <div>
                            <button type="submit" class="esb-portal__button esb-portal__button--primary">Create manual admission</button>
                        </div>
                    </form>

                    <ul class="esb-studio__availability-list mt-4">
                        @forelse ($orders as $order)
                            <li class="esb-studio__availability-item">
                                <span class="esb-studio__availability-name">{{ $order->buyer_name ?: 'Order' }} · {{ $order->quantity }}</span>
                                <span class="esb-studio__availability-status">{{ str($order->channel)->replace('_', ' ')->title() }} · {{ str($order->status)->replace('_', ' ')->title() }} · payment {{ str($order->payment_status)->replace('_', ' ') }}</span>
                                @foreach ($order->tickets as $ticket)
                                    <span class="esb-studio__availability-notes">{{ $ticket->public_id }}{{ $ticket->checkIn ? ' · checked in · '.$configuration->formatInstant($ticket->checkIn->checked_in_at) : ' · not arrived' }}</span>
                                @endforeach
                            </li>
                        @empty
                            <li class="esb-studio__card-body">No orders yet.</li>
                        @endforelse
                    </ul>
                </section>
            @endif
        </div>

        <footer class="esb-studio__chrome-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="esb-portal__button esb-portal__button--secondary">Log out</button>
            </form>
        </footer>
    </main>
@endsection
