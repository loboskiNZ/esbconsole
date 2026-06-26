@extends('layouts.portal')

@section('title', 'Add Performance — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">Add Performance</h1>
            <p class="esb-studio__card-body mt-2">Schedule a dated occurrence for a show.</p>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('studio.performances.index') }}" class="esb-studio__back-link">← Back to Performances</a>
            </div>

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

                @include('studio.performances.partials._form', ['shows' => $shows])

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
