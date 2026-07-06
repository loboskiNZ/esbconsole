@extends('layouts.portal')

@section('title', 'The Studio — Ed and the Shadow Boys')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">The Studio</h1>
        </header>

        <div class="esb-studio__shell-body">
            @if (session('profile_updated'))
                <p class="esb-portal__success mb-4" role="status">
                    Your profile details have been saved.
                </p>
            @endif

            @if (session('invite_created'))
                <p class="esb-portal__success mb-4" role="status">
                    Band invite created.
                </p>
            @endif

            <div class="esb-studio__layout">
                @if ($person)
                    <aside class="esb-studio__sidebar">
                        @include('studio.profile._identity-widget', ['person' => $person])
                        @if ($isDirector)
                            @include('studio.partials._music-library-dashboard-card', [
                                'musicLibrarySummary' => $musicLibrarySummary,
                            ])
                            @include('studio.partials._director-tools')
                            @include('studio.partials._band-invites', [
                                'bandInvites' => $bandInvites,
                                'legacyUnusableInviteCount' => $legacyUnusableInviteCount,
                            ])
                        @endif
                    </aside>
                @endif

                <div class="esb-studio__workspace">
                    <section class="esb-studio__hero" aria-labelledby="studio-hero-title">
                        <div class="esb-studio__hero-copy">
                            <h2 id="studio-hero-title" class="esb-studio__hero-title">Welcome to Studio</h2>
                            <p class="esb-studio__hero-lead">
                                This is your rehearsal workspace.
                            </p>
                            <p class="esb-studio__hero-body">
                                Access charts, performances and schedules.
                            </p>
                        </div>

                        <div class="esb-studio__hero-actions">
                            <article
                                class="esb-portal__panel esb-studio__hero-card esb-studio__hero-card--charts"
                                x-data="studioChartsLauncher(@js(route('studio.charts.search')))"
                                @click.outside="open = false"
                            >
                                <div class="esb-studio__hero-card-shine" aria-hidden="true"></div>

                                <div class="esb-studio__hero-card-head">
                                    @include('studio.partials.icons.music-stand')
                                    <div>
                                        <h3 class="esb-studio__hero-card-title">All Charts</h3>
                                        <p class="esb-studio__hero-card-tagline">Your rehearsal library</p>
                                    </div>
                                </div>

                                <dl class="esb-studio__hero-counts">
                                    <div>
                                        <dt class="sr-only">Songs</dt>
                                        <dd>{{ number_format($songCount) }} Songs</dd>
                                    </div>
                                    <div>
                                        <dt class="sr-only">Charts</dt>
                                        <dd>{{ number_format($chartCount) }} Charts</dd>
                                    </div>
                                </dl>

                                <div class="esb-studio__hero-search">
                                    <label class="sr-only" for="studio-chart-search">Search songs</label>
                                    <input
                                        id="studio-chart-search"
                                        type="search"
                                        class="esb-portal__input esb-studio__hero-search-input"
                                        placeholder="Search songs"
                                        autocomplete="off"
                                        spellcheck="false"
                                        x-model="query"
                                        @keydown="onSearchKeydown($event)"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        :aria-expanded="open ? 'true' : 'false'"
                                        aria-controls="studio-chart-search-results"
                                        :aria-activedescendant="activeIndex >= 0 ? resultId(activeIndex) : null"
                                    >

                                    <ul
                                        id="studio-chart-search-results"
                                        class="esb-studio__hero-search-results"
                                        role="listbox"
                                        x-show="open"
                                        x-cloak
                                    >
                                        <template x-for="(result, index) in results" :key="result.song_id">
                                            <li
                                                :id="resultId(index)"
                                                role="option"
                                                class="esb-studio__hero-search-result"
                                                :class="{ 'esb-studio__hero-search-result--active': index === activeIndex }"
                                                @mouseenter="activeIndex = index"
                                                @mousedown.prevent="selectResult(result)"
                                            >
                                                <span class="esb-studio__hero-search-result-title" x-text="result.name"></span>
                                                <span
                                                    class="esb-studio__hero-search-result-parts"
                                                    x-show="result.parts && result.parts.length"
                                                    x-text="result.parts.join(' · ')"
                                                ></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <div class="esb-studio__hero-card-foot">
                                    <a
                                        href="{{ route('studio.charts.index') }}"
                                        class="esb-portal__button esb-portal__button--primary esb-studio__hero-action"
                                    >
                                        View
                                    </a>
                                </div>
                            </article>

                            <article class="esb-portal__panel esb-studio__hero-card esb-studio__hero-card--secondary esb-studio__hero-card--shows">
                                <div class="esb-studio__hero-card-head">
                                    <svg class="esb-studio__hero-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M7 4h10v3H7V4Z" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M6 7h12v13H6V7Z" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M9 11h6M9 14h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                    <div>
                                        <h3 class="esb-studio__hero-card-title">Shows</h3>
                                        <p class="esb-studio__hero-card-tagline">Shows</p>
                                    </div>
                                </div>

                                @if ($shows->isEmpty())
                                    <p class="esb-studio__hero-card-body">No shows yet.</p>
                                @else
                                    <ul class="esb-studio__shows-list">
                                        @foreach ($shows as $show)
                                            <li class="esb-studio__shows-item">
                                                <div class="esb-studio__shows-row esb-studio__shows-row--compact">
                                                    <a href="{{ route('studio.shows.show', $show) }}" class="esb-studio__shows-link esb-studio__shows-row-main">
                                                        <span class="esb-studio__shows-name">{{ $show->name }}</span>
                                                        <span class="esb-studio__shows-meta">{{ $show->statusLabel() }}</span>
                                                    </a>
                                                    @if ($isDirector)
                                                        @include('studio.shows.partials._show-actions', ['show' => $show])
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="esb-studio__hero-card-foot">
                                    <a
                                        href="{{ route('studio.shows.index') }}"
                                        class="esb-portal__button esb-portal__button--secondary esb-studio__hero-action"
                                    >
                                        Open
                                    </a>
                                </div>
                            </article>

                            <article
                                class="esb-portal__panel esb-studio__hero-card esb-studio__hero-card--secondary esb-studio__hero-card--schedule"
                                x-data="studioSchedule(@js($scheduleItems->pluck('card')->values()), @js($hasMusicianLink))"
                            >
                                <div class="esb-studio__hero-card-head">
                                    <svg class="esb-studio__hero-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M12 7.5v5l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                    <div>
                                        <h3 class="esb-studio__hero-card-title">Schedule</h3>
                                        <p class="esb-studio__hero-card-tagline">Upcoming performances</p>
                                    </div>
                                </div>

                                @if (session('rsvp_saved'))
                                    <p class="esb-portal__success mt-3" role="status">RSVP saved.</p>
                                @endif

                                @if (session('rsvp_error'))
                                    <p class="esb-portal__error mt-3" role="alert">{{ session('rsvp_error') }}</p>
                                @endif

                                @if ($scheduleItems->isEmpty())
                                    <p class="esb-studio__hero-card-body">No upcoming performances.</p>
                                @else
                                    <ul class="esb-studio__schedule-list mt-3">
                                        @foreach ($scheduleItems as $item)
                                            @include('studio.partials._schedule-item', ['item' => $item])
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="esb-studio__hero-card-foot">
                                    <a
                                        href="{{ route('studio.calendar.index') }}"
                                        class="esb-portal__button esb-portal__button--secondary esb-studio__hero-action"
                                    >
                                        View Calendar
                                    </a>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="esb-studio__secondary" aria-labelledby="studio-secondary-title">
                        <h2 id="studio-secondary-title" class="esb-studio__secondary-title">Information</h2>

                        <div class="esb-studio__secondary-grid">
                            <section class="esb-portal__panel esb-studio__card esb-studio__secondary-card">
                                <h3 class="esb-studio__card-title">Band notices</h3>
                                <p class="esb-studio__card-body mt-3">
                                    Announcements, rehearsal updates, and touring notes will land here.
                                </p>
                                <p class="esb-studio__card-note mt-4">Coming in later phases.</p>
                            </section>

                            <section class="esb-portal__panel esb-studio__card esb-studio__secondary-card">
                                <h3 class="esb-studio__card-title">Travel information</h3>
                                <p class="esb-studio__card-body mt-3">
                                    Tour travel details and logistics will appear here.
                                </p>
                                <p class="esb-studio__card-note mt-4">Coming in later phases.</p>
                            </section>

                            <section class="esb-portal__panel esb-studio__card esb-studio__secondary-card">
                                <h3 class="esb-studio__card-title">Tour information</h3>
                                <ul class="esb-studio__list mt-3">
                                    <li>Passport information</li>
                                    <li>Bank account details</li>
                                    <li>Touring information</li>
                                </ul>
                                <p class="esb-studio__card-note mt-4">Coming in later phases.</p>
                            </section>
                        </div>
                    </section>
                </div>
            </div>
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
