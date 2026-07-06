@extends('layouts.portal')

@section('title', ($performance->show?->name ?? 'Performance').' — Performances — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        @include('studio.partials._chrome-header', [
            'pageTitle' => $performance->show?->name ?? 'Performance',
            'pageLead' => $performance->typeLabel().' · '.$performance->statusLabel(),
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('studio')],
                ['label' => 'Schedule', 'url' => route('studio.calendar.index')],
                ['label' => $performance->show?->name ?? 'Performance'],
            ],
        ])

        <div class="esb-studio__shell-body">
            @if ($isDirector)
                <div class="esb-studio__charts-nav mb-4 flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('studio.performances.edit', $performance) }}" class="esb-studio__show-pill esb-studio__show-pill--action">
                        Edit
                    </a>
                </div>
            @endif

            @if (session('performance_created'))
                <p class="esb-portal__success mb-4" role="status">Performance created.</p>
            @endif

            @if (session('performance_updated'))
                <p class="esb-portal__success mb-4" role="status">Performance updated.</p>
            @endif

            @if (session('rsvp_saved'))
                <p class="esb-portal__success mb-4" role="status">RSVP saved.</p>
            @endif

            @if (session('rsvp_error'))
                <p class="esb-portal__error mb-4" role="alert">{{ session('rsvp_error') }}</p>
            @endif

            <section
                class="esb-portal__panel esb-studio__card esb-studio__show-section"
                x-data="studioSchedule([@js($scheduleCard)], @js($hasMusicianLink))"
                x-init="openRsvp(@js($scheduleCard))"
            >
                <div class="esb-studio__schedule-rsvp-head">
                    <h2 class="esb-studio__card-title">Your RSVP</h2>
                    <span class="esb-studio__schedule-rsvp">RSVP: {{ $rsvpLabel }}</span>
                </div>
                <div class="esb-studio__schedule-item-actions mt-4">
                    <a href="{{ $scheduleCard['ics_url'] }}" class="esb-studio__show-pill esb-studio__show-pill--action">
                        Add to calendar
                    </a>
                </div>
                @include('studio.partials._rsvp-inline', ['card' => $scheduleCard, 'alwaysOpen' => true])
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Overview</h2>
                <dl class="esb-studio__show-details mt-4">
                    <div>
                        <dt>Show</dt>
                        <dd>
                            @if ($performance->show)
                                <a href="{{ route('studio.shows.show', $performance->show) }}" class="esb-studio__inline-link">
                                    {{ $performance->show->name }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Type</dt>
                        <dd>{{ $performance->typeLabel() }}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>{{ $performance->statusLabel() }}</dd>
                    </div>
                    <div>
                        <dt>Location</dt>
                        <dd>{{ $performance->locationNameLabel() }}</dd>
                    </div>
                    <div>
                        <dt>Address</dt>
                        <dd>{{ $performance->location_address ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Date</dt>
                        <dd>{{ $performance->formattedPerformanceDate() }}</dd>
                    </div>
                    <div>
                        <dt>Prep time</dt>
                        <dd>{{ $performance->formattedTime($performance->prep_time) }}</dd>
                    </div>
                    <div>
                        <dt>Performance time</dt>
                        <dd>{{ $performance->formattedTime($performance->performance_time) }}</dd>
                    </div>
                    <div>
                        <dt>Duration</dt>
                        <dd>{{ $performance->durationLabel() }}</dd>
                    </div>
                    <div>
                        <dt>Packup time</dt>
                        <dd>{{ $performance->formattedTime($performance->packup_time) }}</dd>
                    </div>
                    <div>
                        <dt>Briefing notes</dt>
                        <dd class="esb-studio__pre-wrap">{{ $performance->briefingNotesLabel() ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">People availability</h2>

                @if ($availabilityAssignments->isEmpty())
                    <p class="esb-studio__card-body mt-3">No availability records yet.</p>
                @else
                    <ul class="esb-studio__availability-list mt-4">
                        @foreach ($availabilityAssignments as $assignment)
                            <li class="esb-studio__availability-item">
                                <span class="esb-studio__availability-name">{{ $assignment->musician?->displayLabel() ?? 'Musician' }}</span>
                                <span class="esb-studio__availability-status">{{ $assignment->availabilityStatusLabel() }}</span>
                                @if ($assignment->availability_notes)
                                    <span class="esb-studio__availability-notes">{{ $assignment->availability_notes }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
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
