@extends('layouts.portal')

@section('title', 'Calendar — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main
        class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col"
        x-data="studioCalendar(@js($scheduleItems->pluck('card')->values()), @js($upcomingItems->pluck('card')->values()), @js($hasMusicianLink), @js($isDirector), @js($performanceCreateUrl))"
    >
        @include('studio.partials._chrome-header', [
            'pageTitle' => 'Calendar',
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('studio')],
                ['label' => 'Schedule'],
            ],
        ])

        <div class="esb-studio__shell-body">
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
                                        <span class="esb-studio__schedule-show" x-text="card.type"></span>
                                        <span class="esb-studio__schedule-meta" x-text="card.show_name + ' · ' + card.status"></span>
                                        <span class="esb-studio__schedule-meta" x-text="card.date + (cardSecondaryMeta(card) ? ' · ' + cardSecondaryMeta(card) : '')"></span>
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
                            <section class="esb-studio__calendar-day @if ($isDirector) is-director @endif" :class="{ 'is-today': isToday(day) }">
                                <div class="esb-studio__calendar-day-header">
                                    @if ($isDirector)
                                        <a
                                            :href="createPerformanceUrl(day)"
                                            class="esb-studio__calendar-date-link"
                                            :aria-label="addPerformanceLabel(day)"
                                        >
                                            <h2 class="esb-studio__calendar-day-label" x-text="dayLabel(day)"></h2>
                                        </a>
                                    @else
                                        <h2 class="esb-studio__calendar-day-label" x-text="dayLabel(day)"></h2>
                                    @endif
                                </div>
                                <template x-if="performancesForDate(day).length === 0">
                                    <p class="esb-studio__calendar-empty">—</p>
                                </template>
                                <template x-for="card in performancesForDate(day)" :key="card.id">
                                    <article class="esb-studio__calendar-event" :class="{ 'esb-studio__calendar-event--rsvp-open': isRsvpOpen(card) }">
                                        <a :href="card.show_url" class="esb-studio__calendar-event-link">
                                            <span class="esb-studio__calendar-event-title" x-text="card.type"></span>
                                            <span
                                                class="esb-studio__calendar-event-meta"
                                                x-show="cardSecondaryMeta(card)"
                                                x-text="cardSecondaryMeta(card)"
                                            ></span>
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
                                    class="esb-studio__calendar-month-cell @if ($isDirector) is-director @endif"
                                    :class="{
                                        'is-muted': !isSameMonth(day),
                                        'is-today': isToday(day),
                                    }"
                                >
                                    @if ($isDirector)
                                        <a
                                            :href="createPerformanceUrl(day)"
                                            class="esb-studio__calendar-date-link"
                                            :aria-label="addPerformanceLabel(day)"
                                        >
                                            <div class="esb-studio__calendar-month-day" x-text="day.getDate()"></div>
                                        </a>
                                    @else
                                        <div class="esb-studio__calendar-month-day" x-text="day.getDate()"></div>
                                    @endif
                                    <template x-for="card in performancesForDate(day).slice(0, 2)" :key="card.id">
                                        <a :href="card.show_url" class="esb-studio__calendar-month-event">
                                            <span class="esb-studio__calendar-month-event-type" x-text="card.type"></span>
                                            <span
                                                class="esb-studio__calendar-month-event-meta"
                                                x-show="cardSecondaryMeta(card)"
                                                x-text="cardSecondaryMeta(card)"
                                            ></span>
                                        </a>
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
