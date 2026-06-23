<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Ed and the Shadow Boys member portal">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Ed and the Shadow Boys Portal')</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body @yield('body-attributes')>
        <div class="fixed inset-0 -z-20 overflow-hidden" aria-hidden="true">
            @isset($backgroundLoad)
                <template x-for="(image, index) in backgroundImages" :key="image">
                    <img
                        :src="image"
                        alt=""
                        class="esb-portal__background-image absolute inset-0 h-full w-full esb-portal__background-layer"
                        :class="[
                            isBackgroundActive(index) && bgVisible ? 'opacity-100' : 'opacity-0',
                        ]"
                        x-ref="index === 0 ? 'backgroundImage' : undefined"
                        @load="index === 0 ? onBackgroundLoaded() : null"
                    >
                </template>
            @else
                <img
                    src="{{ asset('images/portal/ESB-Lobofest3.jpg') }}"
                    alt=""
                    class="esb-portal__background-image absolute inset-0 h-full w-full opacity-100"
                >
            @endisset
            <div
                @isset($backgroundLoad)
                    class="esb-portal__overlay absolute inset-0"
                    :class="overlayVisible ? 'opacity-100' : 'opacity-0'"
                @else
                    class="esb-portal__overlay absolute inset-0 opacity-100"
                @endisset
            ></div>
        </div>

        <div
            class="pointer-events-none fixed inset-0 z-0 flex items-center justify-center"
            aria-hidden="true"
        >
            <img
                src="{{ asset('images/portal/Logo_ESB_BLACKBG.png') }}"
                alt=""
                @isset($backgroundLoad)
                    class="esb-portal__logo esb-portal-logo object-contain"
                    :class="logoVisible ? 'opacity-[0.14]' : 'opacity-0'"
                @else
                    class="esb-portal__logo esb-portal-logo object-contain opacity-[0.14]"
                @endisset
            >
        </div>

        @yield('content')
    </body>
</html>
