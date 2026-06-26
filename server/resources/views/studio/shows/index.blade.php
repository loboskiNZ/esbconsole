@extends('layouts.portal')

@section('title', 'Shows — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">Shows</h1>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('studio') }}" class="esb-studio__back-link">← Back to Studio</a>
            </div>

            @if ($shows->isEmpty())
                <section class="esb-portal__panel esb-studio__card">
                    <p class="esb-studio__card-body">No shows yet.</p>
                </section>
            @else
                <section class="esb-portal__panel esb-studio__card">
                    <ul class="esb-studio__shows-page-list">
                        @foreach ($shows as $show)
                            <li class="esb-studio__shows-page-item">
                                <a href="{{ route('studio.shows.show', $show) }}" class="esb-studio__shows-page-link">
                                    <span class="esb-studio__shows-name">{{ $show->name }}</span>
                                    <span class="esb-studio__shows-meta">
                                        @if ($show->scheduleLabel())
                                            {{ $show->scheduleLabel() }}
                                        @endif
                                        @if ($show->venue_location)
                                            @if ($show->scheduleLabel()) · @endif
                                            {{ $show->venue_location }}
                                        @endif
                                        · {{ $show->statusLabel() }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
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
