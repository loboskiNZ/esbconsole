<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-zinc-950 text-zinc-100 h-full overflow-hidden flex flex-col">
        @if (session('status'))
            <div class="bg-emerald-950 border-b border-emerald-800 text-emerald-200 px-4 py-1.5 text-xs text-center shrink-0">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex-1 min-h-0">
            {{ $slot }}
        </div>
    </body>
</html>
