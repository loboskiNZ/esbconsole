<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $song->song_code }} — {{ $song->name }}
            </h2>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('songs.edit', $song) }}" class="text-indigo-600 hover:text-indigo-800">Edit Song</a>
                <a href="{{ route('songs.index') }}" class="text-gray-600 hover:text-gray-800">← Songs</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 song-detail-shell">
            <div class="song-detail-columns">
                {{-- Song details / metadata --}}
                <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg song-detail-section">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold text-lg mb-4">Song Details</h3>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500">Song code</dt>
                                <dd class="font-medium">{{ $song->song_code }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Runtime identity</dt>
                                <dd class="font-medium">{{ $song->song_code }}.CCC</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Status</dt>
                                <dd class="font-medium">{{ $song->status }}</dd>
                            </div>
                            @if ($song->bpm)
                                <div>
                                    <dt class="text-gray-500">BPM</dt>
                                    <dd class="font-medium">{{ $song->bpm }}</dd>
                                </div>
                            @endif
                            @if ($song->timeSignature)
                                <div>
                                    <dt class="text-gray-500">Time signature</dt>
                                    <dd class="font-medium">{{ $song->timeSignature->label }}</dd>
                                </div>
                            @endif
                            @if ($song->musicalKey)
                                <div>
                                    <dt class="text-gray-500">Key</dt>
                                    <dd class="font-medium">{{ $song->musicalKey->label }}</dd>
                                </div>
                            @endif
                            @if ($song->mood)
                                <div>
                                    <dt class="text-gray-500">Mood</dt>
                                    <dd class="font-medium flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $song->mood->colour_hex }}"></span>
                                        {{ $song->mood->name }}
                                    </dd>
                                </div>
                            @endif
                            @if ($song->director_notes)
                                <div>
                                    <dt class="text-gray-500">Director notes</dt>
                                    <dd class="text-gray-700 break-words whitespace-pre-wrap">{{ $song->director_notes }}</dd>
                                </div>
                            @endif
                            @if ($song->description)
                                <div>
                                    <dt class="text-gray-500">Description</dt>
                                    <dd class="text-gray-700 break-words">{{ $song->description }}</dd>
                                </div>
                            @endif
                            @if ($song->notes)
                                <div>
                                    <dt class="text-gray-500">Notes</dt>
                                    <dd class="text-gray-700 break-words">{{ $song->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </section>

                {{-- Cues --}}
                <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg song-detail-section">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold text-lg mb-4">Cues</h3>

                        @if ($song->cues->isEmpty())
                            <p class="text-sm text-gray-600 mb-4">No cues yet. Add Cue 000 for preparation, then section cues.</p>
                        @else
                            <ul class="divide-y divide-gray-100 mb-6">
                                @foreach ($song->cues as $cue)
                                    <li class="py-2 flex items-start justify-between gap-2 text-sm">
                                        <span class="min-w-0 break-words">
                                            <strong>{{ $cue->runtimeIdentity() }}</strong> — {{ $cue->name }}
                                        </span>
                                        <form method="POST" action="{{ route('songs.cues.destroy', [$song, $cue]) }}" onsubmit="return confirm('Delete this cue?');" class="shrink-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-800">Remove</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('songs.cues.store', $song) }}" class="space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="cue_number" value="Cue number (CCC)" />
                                <x-text-input id="cue_number" name="cue_number" type="text" maxlength="3" class="mt-1 block w-full" placeholder="000" required />
                                <x-input-error :messages="$errors->get('cue_number')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="cue_name" value="Cue name" />
                                <x-text-input id="cue_name" name="name" type="text" class="mt-1 block w-full" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <x-primary-button type="submit" class="w-full justify-center">Add Cue</x-primary-button>
                        </form>
                    </div>
                </section>

                {{-- Instrument parts --}}
                <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg song-detail-section">
                    <div class="p-6 text-gray-900">
                        <div class="flex items-start justify-between gap-2 mb-4">
                            <h3 class="font-semibold text-lg">Instrument Parts</h3>
                            <a href="{{ route('instrument-parts.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 shrink-0">Catalog →</a>
                        </div>

                        @if ($song->songInstrumentParts->isEmpty())
                            <p class="text-sm text-gray-600 mb-4">No instrument parts assigned to this song.</p>
                        @else
                            <ul class="divide-y divide-gray-100 mb-6">
                                @foreach ($song->songInstrumentParts as $sip)
                                    <li class="py-2 flex items-start justify-between gap-2 text-sm">
                                        <span class="min-w-0 break-words">
                                            <strong>{{ $sip->instrumentPart->name }}</strong>
                                            @if ($sip->chart)
                                                · Chart: {{ $sip->chart->title }}
                                            @else
                                                · <span class="text-gray-500">No chart</span>
                                            @endif
                                        </span>
                                        <form method="POST" action="{{ route('songs.instrument-parts.destroy', [$song, $sip]) }}" onsubmit="return confirm('Remove this instrument part?');" class="shrink-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-800">Remove</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($availableInstrumentParts->isEmpty())
                            <p class="text-sm text-gray-600">
                                <a href="{{ route('instrument-parts.index') }}" class="text-indigo-600 hover:text-indigo-800">Create instrument parts</a>
                                before assigning them to this song.
                            </p>
                        @else
                            <form method="POST" action="{{ route('songs.instrument-parts.store', $song) }}" class="space-y-4">
                                @csrf
                                <div>
                                    <x-input-label for="instrument_part_id" value="Instrument part" />
                                    <select id="instrument_part_id" name="instrument_part_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                        <option value="">Select…</option>
                                        @foreach ($availableInstrumentParts as $part)
                                            <option value="{{ $part->id }}">{{ $part->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('instrument_part_id')" class="mt-2" />
                                </div>
                                <x-primary-button type="submit" class="w-full justify-center">Assign Part</x-primary-button>
                            </form>
                        @endif
                    </div>
                </section>

                {{-- Charts --}}
                <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg song-detail-section">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold text-lg mb-4">Charts</h3>

                        @if ($song->charts->isEmpty())
                            <p class="text-sm text-gray-600 mb-4">No charts uploaded for this song yet.</p>
                        @else
                            <div class="space-y-4 mb-6">
                                @foreach ($song->charts as $chart)
                                    <div class="border border-gray-100 rounded-md p-4">
                                        <p class="font-medium text-sm break-words">{{ $chart->title }}</p>
                                        <p class="text-xs text-gray-500 mb-3 break-all">{{ $chart->storage_reference }}</p>

                                        @if ($song->songInstrumentParts->isNotEmpty())
                                            <form method="POST" action="{{ route('charts.assign', $chart) }}">
                                                @csrf
                                                <p class="text-xs text-gray-600 mb-2">Assign to instrument parts:</p>
                                                <div class="space-y-2 mb-3">
                                                    @foreach ($song->songInstrumentParts as $sip)
                                                        <label class="flex items-center gap-2 text-sm">
                                                            <input type="checkbox" name="song_instrument_part_ids[]" value="{{ $sip->id }}"
                                                                @checked($sip->chart_id === $chart->id)>
                                                            <span class="break-words">{{ $sip->instrumentPart->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                <x-primary-button type="submit" class="w-full justify-center text-xs">Assign Chart</x-primary-button>
                                            </form>
                                        @else
                                            <p class="text-xs text-gray-500">Assign instrument parts before linking charts.</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('songs.charts.store', $song) }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="chart_title" value="Chart title" />
                                <x-text-input id="chart_title" name="title" type="text" class="mt-1 block w-full" required />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="chart_file" value="Chart file (PDF or image)" />
                                <input id="chart_file" name="chart" type="file" class="mt-1 block w-full text-sm" required accept=".pdf,.png,.jpg,.jpeg,.webp" />
                                <x-input-error :messages="$errors->get('chart')" class="mt-2" />
                            </div>
                            <x-primary-button type="submit" class="w-full justify-center">Upload Chart</x-primary-button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
