@extends('layouts.portal')

@section('title', 'Manage Band — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">Manage Band</h1>
            <p class="esb-studio__card-body mt-2">Update your band profile, branding, and public-facing details.</p>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('studio') }}" class="esb-studio__back-link">← Back to Studio</a>
            </div>

            @if (session('band_updated'))
                <p class="esb-portal__success mb-4" role="status">
                    Band profile saved.
                </p>
            @endif

            <form
                class="esb-portal__panel esb-studio__card esb-studio__band-form"
                method="POST"
                action="{{ route('studio.band.update') }}"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="esb-portal__error mb-6" role="alert">
                        <p class="font-semibold">Please review the highlighted fields.</p>
                        <ul class="esb-studio__users-error-list mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="esb-studio__band-form-grid">
                    <div>
                        <label class="esb-portal__label mb-2 block" for="band-name">Band name</label>
                        <input
                            id="band-name"
                            name="name"
                            type="text"
                            class="esb-portal__input"
                            value="{{ old('name', $band->name) }}"
                            required
                        >
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="band-bio">Bio</label>
                        <textarea
                            id="band-bio"
                            name="bio"
                            rows="6"
                            class="esb-portal__input esb-studio__band-textarea"
                        >{{ old('bio', $band->bio) }}</textarea>
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="band-styles">Styles</label>
                        <textarea
                            id="band-styles"
                            name="styles"
                            rows="4"
                            class="esb-portal__input esb-studio__band-textarea"
                            placeholder="One style per line or comma-separated"
                        >{{ old('styles', $stylesInput) }}</textarea>
                        <p class="esb-studio__card-note mt-2">Examples: Ska, Latin, Rock</p>
                    </div>

                    <div class="esb-studio__band-upload">
                        <label class="esb-portal__label mb-3 block" for="band-logo">Band logo</label>
                        <div class="esb-studio__band-upload-row">
                            @if ($band->logo_path)
                                <img
                                    src="{{ route('studio.band.logo') }}"
                                    alt="Current band logo"
                                    class="esb-studio__band-asset-preview esb-studio__band-asset-preview--logo"
                                >
                            @endif
                            <div class="min-w-0 flex-1">
                                <input
                                    id="band-logo"
                                    name="logo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,image/svg+xml"
                                    class="esb-portal__input"
                                >
                                <p class="esb-studio__card-note mt-2">JPG, PNG, WebP, or SVG up to 5 MB.</p>
                            </div>
                        </div>
                    </div>

                    <div class="esb-studio__band-upload">
                        <label class="esb-portal__label mb-3 block" for="band-photo">Band photo</label>
                        <div class="esb-studio__band-upload-row">
                            @if ($band->photo_path)
                                <img
                                    src="{{ route('studio.band.photo') }}"
                                    alt="Current band photo"
                                    class="esb-studio__band-asset-preview"
                                >
                            @endif
                            <div class="min-w-0 flex-1">
                                <input
                                    id="band-photo"
                                    name="photo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="esb-portal__input"
                                >
                                <p class="esb-studio__card-note mt-2">JPG, PNG, or WebP up to 25 MB.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="esb-studio__band-form-actions mt-6">
                    <button type="submit" class="esb-portal__button esb-portal__button--primary">
                        Save band profile
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
