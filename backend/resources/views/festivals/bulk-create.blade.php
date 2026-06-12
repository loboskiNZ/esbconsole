<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bulk Create Festivals — {{ $band->name }}</h2>
            <a href="{{ route('festivals.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to Festivals</a>
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
                                <h4 class="font-medium text-sm mb-2">Created festivals</h4>
                                <ul class="text-sm space-y-1">
                                    @foreach ($bulkResult['created'] as $item)
                                        <li>
                                            <a href="{{ route('festivals.edit', $item['festival_id']) }}" class="text-indigo-700 hover:text-indigo-900">
                                                {{ $item['name'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (! empty($bulkResult['skipped']))
                            <div>
                                <h4 class="font-medium text-sm mb-2">Skipped festivals</h4>
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
                    <p class="text-sm text-gray-600 mb-2">Paste one festival per line using this format:</p>
                    <p class="text-xs font-mono text-gray-500 mb-6">Festival Name | Country | City | Website | Contact Name | Phone | Email | Application URL | Application Deadline | Festival Date Notes | Status | Facebook | Instagram | TikTok</p>

                    <form method="POST" action="{{ route('festivals.bulk-store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="festival_lines" value="Festivals" />
                            <textarea
                                id="festival_lines"
                                name="festival_lines"
                                rows="12"
                                class="mt-1 block w-full font-mono text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                placeholder="Bay of Islands Music Festival | New Zealand | Paihia | https://example.com | Sarah Jones | 021 123 456 | sarah@example.com | https://example.com/apply | 2026-08-31 | Usually held in January | not_applied | @boimusic | @boimusicnz | @boimusic"
                                required
                            >{{ old('festival_lines') }}</textarea>
                            <p class="text-xs text-gray-500 mt-2">Invalid or blank status defaults to not_applied.</p>
                            <x-input-error :messages="$errors->get('festival_lines')" class="mt-2" />
                        </div>

                        <x-primary-button>Bulk Create Festivals</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
