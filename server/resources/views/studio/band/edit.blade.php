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

                <section class="esb-studio__band-section" aria-labelledby="band-section-identity">
                    <h2 id="band-section-identity" class="esb-studio__band-section-title">Identity</h2>
                    <div class="esb-studio__band-form-grid">
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-name">Band name</label>
                            <input id="band-name" name="name" type="text" class="esb-portal__input" value="{{ old('name', $band->name) }}" required>
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-short-name">Short name</label>
                            <input id="band-short-name" name="short_name" type="text" class="esb-portal__input" value="{{ old('short_name', $band->short_name) }}">
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-tagline">Tagline</label>
                            <input id="band-tagline" name="tagline" type="text" class="esb-portal__input" value="{{ old('tagline', $band->tagline) }}">
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-hometown">Hometown</label>
                            <input id="band-hometown" name="hometown" type="text" class="esb-portal__input" value="{{ old('hometown', $band->hometown) }}">
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-formation-year">Formation year</label>
                            <input id="band-formation-year" name="formation_year" type="number" min="1900" max="{{ date('Y') + 1 }}" class="esb-portal__input" value="{{ old('formation_year', $band->formation_year) }}">
                        </div>
                    </div>
                </section>

                <section class="esb-studio__band-section" aria-labelledby="band-section-biography">
                    <h2 id="band-section-biography" class="esb-studio__band-section-title">Biography</h2>
                    <div class="esb-studio__band-form-grid">
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-short-bio">Short bio</label>
                            <textarea id="band-short-bio" name="short_bio" rows="4" class="esb-portal__input esb-studio__band-textarea">{{ old('short_bio', $band->short_bio) }}</textarea>
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-full-bio">Full bio</label>
                            <textarea id="band-full-bio" name="full_bio" rows="8" class="esb-portal__input esb-studio__band-textarea">{{ old('full_bio', $band->full_bio) }}</textarea>
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-styles">Styles</label>
                            <textarea id="band-styles" name="styles" rows="4" class="esb-portal__input esb-studio__band-textarea" placeholder="One style per line or comma-separated">{{ old('styles', $stylesInput) }}</textarea>
                            <p class="esb-studio__card-note mt-2">Examples: Ska, Latin, Rock</p>
                        </div>
                    </div>
                </section>

                <section class="esb-studio__band-section" aria-labelledby="band-section-contact">
                    <h2 id="band-section-contact" class="esb-studio__band-section-title">Contact</h2>
                    <div class="esb-studio__band-form-grid">
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-booking-email">Booking email</label>
                            <input id="band-booking-email" name="booking_email" type="email" class="esb-portal__input" value="{{ old('booking_email', $band->booking_email) }}">
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-booking-phone">Booking phone</label>
                            <input id="band-booking-phone" name="booking_phone" type="text" class="esb-portal__input" value="{{ old('booking_phone', $band->booking_phone) }}">
                        </div>
                        <div>
                            <label class="esb-portal__label mb-2 block" for="band-website-url">Website</label>
                            <input id="band-website-url" name="website_url" type="url" class="esb-portal__input" value="{{ old('website_url', $band->website_url) }}" placeholder="https://">
                        </div>
                    </div>
                </section>

                <section class="esb-studio__band-section" aria-labelledby="band-section-social">
                    <h2 id="band-section-social" class="esb-studio__band-section-title">Social links</h2>
                    <div class="esb-studio__band-form-grid">
                        @foreach ([
                            'facebook_url' => 'Facebook',
                            'instagram_url' => 'Instagram',
                            'tiktok_url' => 'TikTok',
                            'youtube_url' => 'YouTube',
                            'spotify_url' => 'Spotify',
                            'apple_music_url' => 'Apple Music',
                            'bandcamp_url' => 'Bandcamp',
                        ] as $field => $label)
                            <div>
                                <label class="esb-portal__label mb-2 block" for="band-{{ str_replace('_', '-', $field) }}">{{ $label }}</label>
                                <input
                                    id="band-{{ str_replace('_', '-', $field) }}"
                                    name="{{ $field }}"
                                    type="url"
                                    class="esb-portal__input"
                                    value="{{ old($field, $band->{$field}) }}"
                                    placeholder="https://"
                                >
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="esb-studio__band-section" aria-labelledby="band-section-media">
                    <h2 id="band-section-media" class="esb-studio__band-section-title">Media</h2>
                    <div class="esb-studio__band-form-grid">
                        <div class="esb-studio__band-upload">
                            <label class="esb-portal__label mb-3 block" for="band-logo">Logo</label>
                            <div class="esb-studio__band-upload-row">
                                @if ($band->logo_path)
                                    <img src="{{ route('studio.band.logo') }}" alt="Current band logo" class="esb-studio__band-asset-preview esb-studio__band-asset-preview--logo">
                                @endif
                                <div class="min-w-0 flex-1">
                                    <input id="band-logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="esb-portal__input">
                                    <p class="esb-studio__card-note mt-2">JPG, PNG, WebP, or SVG up to 5 MB.</p>
                                </div>
                            </div>
                        </div>

                        <div class="esb-studio__band-upload">
                            <label class="esb-portal__label mb-3 block" for="band-photo">Main band photo</label>
                            <div class="esb-studio__band-upload-row">
                                @if ($band->photo_path)
                                    <img src="{{ route('studio.band.photo') }}" alt="Current main band photo" class="esb-studio__band-asset-preview">
                                @endif
                                <div class="min-w-0 flex-1">
                                    <input id="band-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="esb-portal__input">
                                    <p class="esb-studio__card-note mt-2">JPG, PNG, or WebP up to 25 MB.</p>
                                </div>
                            </div>
                        </div>

                        <div class="esb-studio__band-upload">
                            <label class="esb-portal__label mb-3 block" for="band-hero-photo">Hero photo</label>
                            <div class="esb-studio__band-upload-row">
                                @if ($band->hero_photo_path)
                                    <img src="{{ route('studio.band.hero') }}" alt="Current hero photo" class="esb-studio__band-asset-preview">
                                @endif
                                <div class="min-w-0 flex-1">
                                    <input id="band-hero-photo" name="hero_photo" type="file" accept="image/jpeg,image/png,image/webp" class="esb-portal__input">
                                    <p class="esb-studio__card-note mt-2">Wide hero image for public surfaces.</p>
                                </div>
                            </div>
                        </div>

                        <div class="esb-studio__band-upload">
                            <label class="esb-portal__label mb-3 block" for="band-press-photo">Press photo</label>
                            <div class="esb-studio__band-upload-row">
                                @if ($band->press_photo_path)
                                    <img src="{{ route('studio.band.press') }}" alt="Current press photo" class="esb-studio__band-asset-preview">
                                @endif
                                <div class="min-w-0 flex-1">
                                    <input id="band-press-photo" name="press_photo" type="file" accept="image/jpeg,image/png,image/webp" class="esb-portal__input">
                                    <p class="esb-studio__card-note mt-2">High-quality press or promo photo.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

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
