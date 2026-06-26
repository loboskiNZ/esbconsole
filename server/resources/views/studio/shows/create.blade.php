@extends('layouts.portal')

@section('title', 'Add Show — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">Add Show</h1>
            <p class="esb-studio__card-body mt-2">Create a reusable show production.</p>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('studio.shows.index') }}" class="esb-studio__back-link">← Back to Shows</a>
            </div>

            <form
                class="esb-portal__panel esb-studio__card esb-studio__show-form"
                method="POST"
                action="{{ route('studio.shows.store') }}"
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

                <div class="esb-studio__band-form-grid">
                    <div>
                        <label class="esb-portal__label mb-2 block" for="show-name">Show name</label>
                        <input id="show-name" name="name" type="text" class="esb-portal__input" value="{{ old('name') }}" required>
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="show-description">Description</label>
                        <textarea id="show-description" name="description" rows="5" class="esb-portal__input esb-studio__band-textarea">{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="show-status">Lifecycle state</label>
                        <select id="show-status" name="lifecycle_state" class="esb-portal__input">
                            <option value="draft" @selected(old('lifecycle_state', 'draft') === 'draft')>Draft</option>
                            <option value="planned" @selected(old('lifecycle_state') === 'planned')>Planned</option>
                        </select>
                    </div>
                </div>

                <div class="esb-studio__band-form-actions mt-6">
                    <button type="submit" class="esb-portal__button esb-portal__button--primary">
                        Create show
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
