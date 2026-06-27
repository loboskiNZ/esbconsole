@extends('layouts.portal')

@section('title', 'Forgot password — Ed and the Shadow Boys Portal')

@section('body-attributes')
    class="esb-portal esb-portal--desktop antialiased"
@endsection

@section('content')
    <main class="relative z-10 flex min-h-dvh flex-col items-center px-4 py-8 sm:px-6">
        <div class="esb-portal__welcome flex w-full max-w-3xl flex-col items-center text-center opacity-100">
            <p class="esb-portal__eyebrow mb-3">Member portal</p>
            <h1 class="esb-portal__title">Forgot your password?</h1>
            <p class="esb-studio__card-body mt-3 max-w-md">
                Enter the email address on your Studio account. We will send a reset link if we find a match.
            </p>
        </div>

        <section class="mt-8 w-full max-w-md">
            <form class="esb-portal__panel rounded-2xl p-6 sm:p-7" method="POST" action="{{ route('password.email') }}">
                @csrf

                @if (session('status'))
                    <p class="esb-portal__success mb-4 text-center" role="status">
                        {{ session('status') }}
                    </p>
                @endif

                @error('email')
                    <p class="esb-portal__error mb-4 text-center" role="alert">{{ $message }}</p>
                @enderror

                <label class="esb-portal__label mb-2 block" for="reset-email">Email address</label>
                <input
                    id="reset-email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    class="esb-portal__input mb-5"
                    value="{{ old('email') }}"
                    required
                >

                <button type="submit" class="esb-portal__button esb-portal__button--primary w-full">
                    Email reset link
                </button>

                <p class="esb-studio__card-body mt-5 text-center">
                    <a href="{{ route('home') }}" class="esb-portal__link">Back to login</a>
                </p>
            </form>
        </section>
    </main>
@endsection
