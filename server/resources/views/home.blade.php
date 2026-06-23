<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Ed and the Shadow Boys member portal">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Ed and the Shadow Boys Portal</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body
        class="esb-portal esb-portal--desktop antialiased"
        x-data="portalLanding(@js(old('username', '')), @js($errors->has('login')))"
        x-cloak
    >
        {{-- Stage 1: Background --}}
        <div class="fixed inset-0 -z-20 overflow-hidden" aria-hidden="true">
            <img
                x-ref="backgroundImage"
                src="{{ asset('images/portal/ESB-Lobofest3.jpg') }}"
                alt=""
                class="esb-portal__background-image absolute inset-0 h-full w-full transition-opacity duration-[1400ms] ease-out"
                :class="bgVisible ? 'opacity-100' : 'opacity-0'"
                @load="onBackgroundLoaded()"
            >
            <div
                class="esb-portal__overlay absolute inset-0"
                :class="overlayVisible ? 'opacity-100' : 'opacity-0'"
            ></div>
        </div>

        {{-- Stage 2: Logo background --}}
        <div
            class="pointer-events-none fixed inset-0 z-0 flex items-center justify-center"
            aria-hidden="true"
        >
            <img
                src="{{ asset('images/portal/Logo_ESB_BLACKBG.png') }}"
                alt=""
                class="esb-portal__logo esb-portal-logo object-contain"
                :class="logoVisible ? 'opacity-[0.14]' : 'opacity-0'"
            >
        </div>

        {{-- Stage 3 + login journey --}}
        <main class="relative z-10 flex min-h-dvh flex-col items-center px-4 py-8 sm:px-6">
            <div
                class="esb-portal__welcome flex w-full max-w-3xl flex-col items-center text-center"
                :class="[
                    welcomeVisible ? 'opacity-100' : 'opacity-0',
                    welcomeSettled ? 'esb-portal__welcome--settled' : '',
                ]"
            >
                <p class="esb-portal__eyebrow mb-3">Member portal</p>
                <h1 class="esb-portal__title">
                    Welcome to the Ed and the Shadow Boys Portal
                </h1>
            </div>

            <section
                class="mt-auto w-full max-w-md pb-4 pt-6 sm:mt-8"
                x-show="loginVisible"
                x-transition:enter="esb-portal-fade-enter"
                x-transition:enter-start="esb-portal-fade-enter-start"
                x-transition:enter-end="esb-portal-fade-enter-end"
            >
                <form
                    class="esb-portal__panel rounded-2xl p-6 sm:p-7"
                    method="POST"
                    action="{{ route('login') }}"
                    x-ref="loginForm"
                    @submit="submitLogin($event)"
                    novalidate
                >
                    @csrf

                    @if ($errors->has('login'))
                        <p class="esb-portal__error mb-4 text-center" role="alert">
                            {{ $errors->first('login') }}
                        </p>
                    @endif

                    @if (! empty($onboardingComplete))
                        <p class="esb-portal__success mb-4 text-center">
                            Your Studio account has been created. Log in to enter The Studio.
                        </p>
                    @endif

                    <input type="hidden" name="username" :value="username">

                    <template x-if="loginStep === 'username'">
                        <div>
                            <p class="esb-portal__label mb-4">Enter your username</p>

                            <label class="sr-only" for="portal-username">Username</label>
                            <input
                                id="portal-username"
                                type="text"
                                autocomplete="username"
                                class="esb-portal__input mb-4"
                                placeholder="Username"
                                x-model="username"
                            >

                            <button
                                type="button"
                                class="esb-portal__button esb-portal__button--primary w-full"
                                :disabled="!username.trim()"
                                @click="continueFromUsername()"
                            >
                                Continue
                            </button>
                        </div>
                    </template>

                    <template x-if="loginStep === 'password'">
                        <div>
                            <p class="esb-portal__label mb-4">Enter your password</p>

                            <label class="sr-only" for="portal-password">Password</label>
                            <input
                                id="portal-password"
                                type="password"
                                name="password"
                                autocomplete="current-password"
                                class="esb-portal__input"
                                placeholder="Password"
                                x-model="password"
                                @input="onPasswordInput()"
                            >

                            <div
                                class="mt-5 space-y-4"
                                x-show="showLoginButton"
                                x-transition.opacity.duration.500ms
                            >
                                <button
                                    type="submit"
                                    class="esb-portal__button esb-portal__button--primary w-full"
                                >
                                    Login
                                </button>

                                <p
                                    class="text-center"
                                    x-show="showForgotPassword"
                                    x-transition.opacity.duration.500ms
                                >
                                    <a href="#" class="esb-portal__link" @click.prevent>Forgot your password?</a>
                                </p>
                            </div>
                        </div>
                    </template>
                </form>
            </section>
        </main>

        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>
    </body>
</html>
