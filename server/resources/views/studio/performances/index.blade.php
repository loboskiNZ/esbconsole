@extends('layouts.portal')

@section('title', 'Performances — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        @include('studio.partials._chrome-header', [
            'pageTitle' => 'Performances',
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('studio')],
                ['label' => 'Performances'],
            ],
        ])

        <div class="esb-studio__shell-body">
            @if ($performances->isEmpty())
                <section class="esb-portal__panel esb-studio__card">
                    <p class="esb-studio__card-body">No performances yet.</p>
                </section>
            @else
                <section class="esb-portal__panel esb-studio__card">
                    <ul class="esb-studio__performances-list">
                        @foreach ($performances as $performance)
                            <li class="esb-studio__performances-item">
                                <a href="{{ route('studio.performances.show', $performance) }}" class="esb-studio__performances-link">
                                    <span class="esb-studio__performances-primary">
                                        <span class="esb-studio__performances-show">{{ $performance->show?->name ?? 'Show' }}</span>
                                        <span class="esb-studio__performances-meta">{{ $performance->typeLabel() }} · {{ $performance->statusLabel() }}</span>
                                    </span>
                                    <span class="esb-studio__performances-secondary">
                                        <span>{{ $performance->formattedPerformanceDate() }}</span>
                                        <span>{{ $performance->locationNameLabel() }}</span>
                                        <span>{{ $performance->formattedTime($performance->performance_time) }}</span>
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
