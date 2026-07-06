@extends('layouts.portal')

@section('title', 'Calendar — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main
        class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col"
        x-data="studioCalendar(@js($scheduleItems->pluck('card')->values()), @js($upcomingItems->pluck('card')->values()), @js($hasMusicianLink))"
    >
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">Calendar</h1>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('studio') }}" class="esb-studio__back-link">← Back to Studio</a>
            </div>

            @if (session('rsvp_saved'))
                <p class="esb-portal__success mb-4" role="status">RSVP saved.</p>
            @endif

            @if (session('rsvp_error'))
                <p class="esb-portal__error mb-4" role="alert">{{ session('rsvp_error') }}</p>
            @endif

            <section class="esb-portal__panel esb-studio__card esb-studio__calendar-shell">
                <div class="esb-studio__calendar-toolbar">
                    <div class="esb-studio__calendar-view-switch">
                        <button type="button" class="esb-studio__calendar-view-btn" :class="{ 'is-active': view === 'list' }" @click="setView('list')">List</button>
                        <button type="button" class="esb-studio__calendar-view-btn" :class="{ 'is-active': view === 'week' }" @click="setView('week')">Week</button>
                        <button type="button" class="esb-studio__calendar-view-btn" :class="{ 'is-active': view === 'month' }" @click="setView('month')">Month</button>
                    </div>

                    <div class="esb-studio__calendar-period" x-show="view !== 'list'" x-cloak>
                        <button type="button" class="esb-studio__calendar-nav-btn" @click="previousPeriod()" aria-label="Previous period">←</button>
                        <span class="esb-studio__calendar-period-label" x-text="periodLabel()"></span>
                        <button type="button" class="esb-studio__calendar-nav-btn" @click="nextPeriod()" aria-label="Next period">→</button>
                    </div>
                </div>

                <div class="esb-studio__calendar-view" x-show="view === 'list'" x-cloak>
                    <template x-if="listItems().length === 0">
                        <p class="esb-studio__card-body">No upcoming performances.</p>
                    </template>
                    <ul class="esb-studio__schedule-list" x-show="listItems().length > 0">
                        <template x-for="card in listItems()" :key="card.id">
                            <li class="esb-studio__schedule-item" :class="{ 'esb-studio__schedule-item--rsvp-open': isRsvpOpen(card) }">
                                <div class="esb-studio__schedule-item-main">
                                    <a :href="card.show_url" class="esb-studio__schedule-item-link">
                                        <span class="esb-studio__schedule-show" x-text="card.show_name"></span>
                                        <span class="esb-studio__schedule-meta" x-text="card.type + ' · ' + card.status"></span>
                                        <span class="esb-studio__schedule-meta" x-text="card.date + ' · ' + card.time + ' · ' + card.location"></span>
                                        <span class="esb-studio__schedule-rsvp" x-text="'RSVP: ' + card.rsvp_label"></span>
                                    </a>
                                </div>
                                <div class="esb-studio__schedule-item-actions">
                                    <button
                                        type="button"
                                        class="esb-studio__show-pill esb-studio__show-pill--action"
                                        :class="{ 'esb-studio__show-pill--active': isRsvpOpen(card) }"
                                        :aria-expanded="isRsvpOpen(card)"
                                        @click="toggleRsvp(card)"
                                    >
                                        <span x-text="isRsvpOpen(card) ? 'Close' : 'RSVP'"></span>
                                    </button>
                                    <a :href="card.ics_url" class="esb-studio__show-pill esb-studio__show-pill--action">Add to calendar</a>
                                    <a :href="card.show_url" class="esb-studio__show-pill esb-studio__show-pill--action">View</a>
                                </div>
                                @include('studio.partials._rsvp-inline', ['useAlpineCard' => true])
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="esb-studio__calendar-view" x-show="view === 'week'" x-cloak>
                    <div class="esb-studio__calendar-week">
                        <template x-for="day in weekDays()" :key="day.toISOString()">
                            <section class="esb-studio__calendar-day" :class="{ 'is-today': isToday(day) }">
                                <h2 class="esb-studio__calendar-day-label" x-text="dayLabel(day)"></h2>
                                <template x-if="performancesForDate(day).length === 0">
                                    <p class="esb-studio__calendar-empty">—</p>
                                </template>
                                <template x-for="card in performancesForDate(day)" :key="card.id">
                                    <article class="esb-studio__calendar-event" :class="{ 'esb-studio__calendar-event--rsvp-open': isRsvpOpen(card) }">
                                        <a :href="card.show_url" class="esb-studio__calendar-event-link">
                                            <span class="esb-studio__calendar-event-title" x-text="card.show_name"></span>
                                            <span class="esb-studio__calendar-event-meta" x-text="card.time + ' · ' + card.type"></span>
                                        </a>
                                        <div class="esb-studio__schedule-item-actions">
                                            <button
                                                type="button"
                                                class="esb-studio__show-pill esb-studio__show-pill--action"
                                                :class="{ 'esb-studio__show-pill--active': isRsvpOpen(card) }"
                                                :aria-expanded="isRsvpOpen(card)"
                                                @click="toggleRsvp(card)"
                                            >
                                                <span x-text="isRsvpOpen(card) ? 'Close' : 'RSVP'"></span>
                                            </button>
                                            <a :href="card.show_url" class="esb-studio__show-pill esb-studio__show-pill--action">View</a>
                                        </div>
                                        @include('studio.partials._rsvp-inline', ['useAlpineCard' => true])
                                    </article>
                                </template>
                            </section>
                        </template>
                    </div>
                </div>

                <div class="esb-studio__calendar-view" x-show="view === 'month'" x-cloak>
                    <div class="esb-studio__calendar-month-head" aria-hidden="true">
                        <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                    </div>
                    <template x-for="(week, weekIndex) in monthWeeks()" :key="weekIndex">
                        <div class="esb-studio__calendar-month-week">
                            <template x-for="day in week" :key="day.toISOString()">
                                <section
                                    class="esb-studio__calendar-month-cell"
                                    :class="{
                                        'is-muted': !isSameMonth(day),
                                        'is-today': isToday(day),
                                    }"
                                >
                                    <div class="esb-studio__calendar-month-day" x-text="day.getDate()"></div>
                                    <template x-for="card in performancesForDate(day).slice(0, 2)" :key="card.id">
                                        <a :href="card.show_url" class="esb-studio__calendar-month-event" x-text="card.show_name"></a>
                                    </template>
                                </section>
                            </template>
                        </div>
                    </template>
                </div>
            </section>
        </div>

        <footer class="esb-studio__chrome-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="esb-portal__button esb-portal__button--secondary">
                    Log out
                </button>
            </form>
        </footer>
    </main>
@endsection
