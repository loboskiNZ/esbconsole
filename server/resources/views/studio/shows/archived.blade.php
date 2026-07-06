@extends('layouts.portal')

@section('title', 'Archived Shows — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        @include('studio.partials._chrome-header', [
            'pageTitle' => 'Archived Shows',
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('studio')],
                ['label' => 'Shows', 'url' => route('studio.shows.index')],
                ['label' => 'Archived Shows'],
            ],
        ])

        <div class="esb-studio__shell-body">
            @if ($shows->isEmpty())
                <section class="esb-portal__panel esb-studio__card">
                    <p class="esb-studio__card-body">No archived shows.</p>
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
                                    @include('studio.shows.partials._show-actions', ['show' => $show, 'archived' => true])
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
