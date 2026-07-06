@extends('layouts.portal')

@section('title', 'Shows — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        @include('studio.partials._chrome-header', [
            'pageTitle' => 'Shows',
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('studio')],
                ['label' => 'Shows'],
            ],
        ])

        <div class="esb-studio__shell-body">
            @if ($isDirector)
                <div class="esb-studio__charts-nav mb-4 flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('studio.shows.archived') }}" class="esb-studio__shows-archived-link">
                        View Archived
                    </a>
                </div>
            @endif

            @if ($shows->isEmpty())
                <section class="esb-portal__panel esb-studio__card">
                    <p class="esb-studio__card-body">No shows yet.</p>
                </section>
            @else
                <section class="esb-portal__panel esb-studio__card">
                    <ul class="esb-studio__shows-page-list">
                        @foreach ($shows as $show)
                            <li class="esb-studio__shows-page-item">
                                <div class="esb-studio__shows-row">
                                    <a href="{{ route('studio.shows.show', $show) }}" class="esb-studio__shows-page-link esb-studio__shows-row-main">
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
