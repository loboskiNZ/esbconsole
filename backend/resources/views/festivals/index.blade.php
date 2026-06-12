@php
    $renderFestivalMeta = function ($festival) {
        $location = array_filter([$festival->country, $festival->city]);
        $contact = array_filter([
            $festival->contact_name,
            $festival->contact_phone,
            $festival->contact_email,
        ]);
        $application = array_filter([
            $festival->website ? 'Website: '.$festival->website : null,
            $festival->application_url ? 'Apply: '.$festival->application_url : null,
            $festival->application_deadline ? 'Deadline: '.$festival->application_deadline->format('Y-m-d') : null,
            $festival->application_status ? 'Status: '.$festival->application_status->label() : null,
            $festival->festival_date_notes ? 'Dates: '.$festival->festival_date_notes : null,
        ]);
        $social = array_filter([
            $festival->facebook_tag ? 'Facebook: '.$festival->facebook_tag : null,
            $festival->instagram_tag ? 'Instagram: '.$festival->instagram_tag : null,
            $festival->tiktok_tag ? 'TikTok: '.$festival->tiktok_tag : null,
        ]);

        return compact('location', 'contact', 'application', 'social');
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Festivals — {{ $band->name }}</h2>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('festivals.bulk-create') }}" class="text-indigo-600 hover:text-indigo-800">Bulk Create</a>
                <a href="{{ route('festivals.create') }}" class="text-indigo-600 hover:text-indigo-800">+ New Festival</a>
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
                    <label for="festival-search" class="block text-sm font-medium text-gray-700">Search festivals</label>
                    <input
                        id="festival-search"
                        type="search"
                        x-model="query"
                        placeholder="Search by name, country, city, website, contact, phone, email, application URL, or status…"
                        class="mt-2 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    >
                    <p class="text-xs text-gray-500 mt-2">Filters as you type — no page reload.</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Active Festivals</h3>

                    @if ($activeFestivals->isEmpty())
                        <p class="text-sm text-gray-600">No active festivals yet.</p>
                        <a href="{{ route('festivals.create') }}" class="text-sm text-indigo-600 hover:text-indigo-800 mt-2 inline-block">Create a festival →</a>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($activeFestivals as $festival)
                                @php($meta = $renderFestivalMeta($festival))
                                <li
                                    class="py-4 flex items-start justify-between gap-4"
                                    data-search="{{ $festival->searchText() }}"
                                    x-show="!query.trim() || $el.dataset.search.includes(query.trim().toLowerCase())"
                                >
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900">{{ $festival->name }}</p>
                                        @if ($meta['location'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['location']) }}</p>
                                        @endif
                                        @if ($meta['contact'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['contact']) }}</p>
                                        @endif
                                        @if ($meta['application'] !== [])
                                            <p class="text-sm text-indigo-700 mt-1">{{ implode(' · ', $meta['application']) }}</p>
                                        @endif
                                        @if ($meta['social'] !== [])
                                            <p class="text-sm text-indigo-700 mt-1">{{ implode(' · ', $meta['social']) }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <a href="{{ route('festivals.edit', $festival) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                                        <form method="POST" action="{{ route('festivals.archive', $festival) }}" onsubmit="return confirm('Archive {{ $festival->name }}? The festival will be hidden from the active list but not deleted.');">
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

            @if ($archivedFestivals->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold mb-1">Archived Festivals</h3>
                        <p class="text-sm text-gray-500 mb-4">Archived festivals are hidden from the active list until restored.</p>

                        <ul class="divide-y divide-gray-200">
                            @foreach ($archivedFestivals as $festival)
                                @php($meta = $renderFestivalMeta($festival))
                                <li
                                    class="py-4 flex items-start justify-between gap-4 opacity-80"
                                    data-search="{{ $festival->searchText() }}"
                                    x-show="!query.trim() || $el.dataset.search.includes(query.trim().toLowerCase())"
                                >
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900">{{ $festival->name }} <span class="text-xs font-normal text-gray-500">(Archived)</span></p>
                                        @if ($meta['location'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['location']) }}</p>
                                        @endif
                                        @if ($meta['contact'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['contact']) }}</p>
                                        @endif
                                        @if ($meta['application'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['application']) }}</p>
                                        @endif
                                        @if ($meta['social'] !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $meta['social']) }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <a href="{{ route('festivals.edit', $festival) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                                        <form method="POST" action="{{ route('festivals.restore', $festival) }}">
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
