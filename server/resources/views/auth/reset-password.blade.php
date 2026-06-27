@extends('layouts.portal')

@section('title', 'Reset password — Ed and the Shadow Boys Portal')

@section('body-attributes')
    class="esb-portal esb-portal--desktop antialiased"
@endsection

@section('content')
    <main class="relative z-10 flex min-h-dvh flex-col items-center px-4 py-8 sm:px-6">
        <div class="esb-portal__welcome flex w-full max-w-3xl flex-col items-center text-center opacity-100">
            <p class="esb-portal__eyebrow mb-3">Member portal</p>
            <h1 class="esb-portal__title">Choose a new password</h1>
            <p class="esb-studio__card-body mt-3 max-w-md">
                Enter and confirm your new password below.
            </p>
        </div>

        <section class="mt-8 w-full max-w-md">
            <form class="esb-portal__panel rounded-2xl p-6 sm:p-7" method="POST" action="{{ route('password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                @error('email')
                    <p class="esb-portal__error mb-4 text-center" role="alert">{{ $message }}</p>
                @enderror

                @if ($errors->has('password'))
                    <p class="esb-portal__error mb-4 text-center" role="alert">{{ $errors->first('password') }}</p>
                @endif

                <label class="esb-portal__label mb-2 block" for="reset-password-email">Email address</label>
                <input
                    id="reset-password-email"
                    name="email"
                    type="email"
                    autocomplete="username"
                    class="esb-portal__input mb-4"
                    value="{{ old('email', $email) }}"
                    required
                    readonly
                >

                <label class="esb-portal__label mb-2 block" for="reset-password">New password</label>
                <input
                    id="reset-password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    class="esb-portal__input mb-4"
                    required
                >

                <label class="esb-portal__label mb-2 block" for="reset-password-confirmation">Confirm password</label>
                <input
                    id="reset-password-confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="esb-portal__input mb-5"
                    required
                >

                <button type="submit" class="esb-portal__button esb-portal__button--primary w-full">
                    Reset password
                </button>

                <p class="esb-studio__card-body mt-5 text-center">
                    <a href="{{ route('home') }}" class="esb-portal__link">Back to login</a>
                </p>
            </form>
        </section>
    </main>
@endsection
