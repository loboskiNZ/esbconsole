@extends('layouts.portal')

@section('title', 'Add Performance — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        @include('studio.partials._chrome-header', [
            'pageTitle' => 'Add Performance',
            'pageLead' => 'Schedule a dated occurrence for a show.',
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('studio')],
                ['label' => 'Schedule', 'url' => route('studio.calendar.index')],
                ['label' => 'Add Performance'],
            ],
        ])

        <div class="esb-studio__shell-body">
            <form
                class="esb-portal__panel esb-studio__card esb-studio__performance-form"
                method="POST"
                action="{{ route('studio.performances.store') }}"
            >
                @csrf

                @if ($errors->any())
                    <div class="esb-portal__error mb-6" role="alert">
                        <ul class="esb-studio__users-error-list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @include('studio.performances.partials._form', [
                    'shows' => $shows,
                    'prefillDate' => $prefillDate ?? null,
                ])

                <div class="esb-studio__band-form-actions mt-6">
                    <button type="submit" class="esb-portal__button esb-portal__button--primary">
                        Create performance
                    </button>
                </div>
            </form>
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
