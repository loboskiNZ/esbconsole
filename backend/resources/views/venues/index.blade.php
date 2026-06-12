@php
    $renderVenueMeta = function ($venue) {
        $parts = array_filter([
            $venue->country,
            $venue->city,
            $venue->address,
        ]);
        $contact = array_filter([
            $venue->contact_name,
            $venue->contact_phone,
            $venue->contact_email,
        ]);
        $social = array_filter([
            $venue->facebook_tag ? 'Facebook: '.$venue->facebook_tag : null,
            $venue->instagram_tag ? 'Instagram: '.$venue->instagram_tag : null,
            $venue->tiktok_tag ? 'TikTok: '.$venue->tiktok_tag : null,
        ]);
        return compact('parts', 'contact', 'social');
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Venues — {{ $band->name }}</h2>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('venues.bulk-create') }}" class="text-indigo-600 hover:text-indigo-800">Bulk Create</a>
                <a href="{{ route('venues.create') }}" class="text-indigo-600 hover:text-indigo-800">+ New Venue</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ query: '' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <label for="venue-search" class="block text-sm font-medium text-gray-700">Search venues</label>
                    <input
                        id="venue-search"
                        type="search"
                        x-model="query"
                        placeholder="Search by name, country, city, address, contact, phone, or email…"
                        class="mt-2 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    >
                    <p class="text-xs text-gray-500 mt-2">Filters as you type — no page reload.</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Active Venues</h3>

                    @if ($activeVenues->isEmpty())
                        <p class="text-sm text-gray-600">No active venues yet.</p>
                        <a href="{{ route('venues.create') }}" class="text-sm text-indigo-600 hover:text-indigo-800 mt-2 inline-block">Create a venue →</a>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($activeVenues as $venue)
                                @php($meta = $renderVenueMeta($venue))
                                <li
                                    class="py-4 flex items-start justify-between gap-4"
                                    data-search="{{ $venue->searchText() }}"
                                    x-show="!query.trim() || $el.dataset.search.includes(query.trim().toLowerCase())"
                                >
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900">{{ $venue->name }}</p>
                                        @if ($meta['parts'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['parts']) }}</p>
                                        @endif
                                        @if ($meta['contact'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['contact']) }}</p>
                                        @endif
                                        @if ($meta['social'] !== [])
                                            <p class="text-sm text-indigo-700 mt-1">{{ implode(' · ', $meta['social']) }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <a href="{{ route('venues.edit', $venue) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                                        <form method="POST" action="{{ route('venues.archive', $venue) }}" onsubmit="return confirm('Archive {{ $venue->name }}? The venue will be hidden from the active list but not deleted.');">
                                            @csrf
                                            <button type="submit" class="text-sm text-amber-700 hover:text-amber-900">Archive</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            @if ($archivedVenues->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold mb-1">Archived Venues</h3>
                        <p class="text-sm text-gray-500 mb-4">Archived venues are hidden from the active list until restored.</p>

                        <ul class="divide-y divide-gray-200">
                            @foreach ($archivedVenues as $venue)
                                @php($meta = $renderVenueMeta($venue))
                                <li
                                    class="py-4 flex items-start justify-between gap-4 opacity-80"
                                    data-search="{{ $venue->searchText() }}"
                                    x-show="!query.trim() || $el.dataset.search.includes(query.trim().toLowerCase())"
                                >
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900">{{ $venue->name }} <span class="text-xs font-normal text-gray-500">(Archived)</span></p>
                                        @if ($meta['parts'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['parts']) }}</p>
                                        @endif
                                        @if ($meta['contact'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['contact']) }}</p>
                                        @endif
                                        @if ($meta['social'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['social']) }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <a href="{{ route('venues.edit', $venue) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                                        <form method="POST" action="{{ route('venues.restore', $venue) }}">
                                            @csrf
                                            <button type="submit" class="text-sm text-green-700 hover:text-green-900">Restore</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
