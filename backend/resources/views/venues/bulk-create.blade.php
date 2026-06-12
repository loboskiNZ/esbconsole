<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bulk Create Venues — {{ $band->name }}</h2>
            <a href="{{ route('venues.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to Venues</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($bulkResult)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 space-y-4">
                        <h3 class="font-semibold text-lg">Bulk Create Result</h3>
                        <p class="text-sm text-gray-600">
                            Created: <strong>{{ $bulkResult['created_count'] }}</strong>
                            · Skipped: <strong>{{ $bulkResult['skipped_count'] }}</strong>
                        </p>

                        @if (! empty($bulkResult['created']))
                            <div>
                                <h4 class="font-medium text-sm mb-2">Created venues</h4>
                                <ul class="text-sm space-y-1">
                                    @foreach ($bulkResult['created'] as $item)
                                        <li>
                                            <a href="{{ route('venues.edit', $item['venue_id']) }}" class="text-indigo-700 hover:text-indigo-900">
                                                {{ $item['name'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (! empty($bulkResult['skipped']))
                            <div>
                                <h4 class="font-medium text-sm mb-2">Skipped venues</h4>
                                <ul class="text-sm space-y-1 text-gray-700">
                                    @foreach ($bulkResult['skipped'] as $item)
                                        <li><strong>{{ $item['name'] }}</strong> — {{ $item['reason'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-2">Paste one venue per line using this format:</p>
                    <p class="text-xs font-mono text-gray-500 mb-6">Venue Name | Country | City | Address | Contact Name | Phone | Email | Facebook | Instagram | TikTok</p>

                    <form method="POST" action="{{ route('venues.bulk-store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="venue_lines" value="Venues" />
                            <textarea
                                id="venue_lines"
                                name="venue_lines"
                                rows="12"
                                class="mt-1 block w-full font-mono text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                placeholder="The Jam Factory | New Zealand | Auckland | 123 Queen St | Sarah Jones | 021 123 456 | sarah@example.com | @jamfactory | @jamfactorynz | @jamfactory"
                                required
                            >{{ old('venue_lines') }}</textarea>
                            <x-input-error :messages="$errors->get('venue_lines')" class="mt-2" />
                        </div>

                        <x-primary-button>Bulk Create Venues</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
