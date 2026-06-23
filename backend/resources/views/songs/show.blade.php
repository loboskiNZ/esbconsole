<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Song Authoring Workspace</p>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $song->song_code }} — {{ $song->name }}
                </h2>
            </div>
            <div class="flex gap-4 text-sm shrink-0">
                <a href="{{ route('songs.edit', $song) }}" class="text-indigo-600 hover:text-indigo-800">Edit Authoring</a>
                <a href="{{ route('songs.index') }}" class="text-gray-600 hover:text-gray-800">← Songs</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 song-workspace-shell">
            @if (session('status'))
                <div class="mb-6 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="song-workspace-layout">
                @include('songs._workspace-nav')

                <div class="song-workspace-content">
                    {{-- 1. Overview --}}
                    <section id="overview" class="song-workspace-section">
                        <div class="song-workspace-section__head">
                            <h3 class="song-workspace-section__title">Overview</h3>
                        </div>
                        <dl class="song-workspace-dl">
                            <div>
                                <dt>Title</dt>
                                <dd>{{ $song->name }}</dd>
                            </div>
                            <div>
                                <dt>Song code</dt>
                                <dd>{{ $song->song_code }}</dd>
                            </div>
                            <div>
                                <dt>Runtime identity</dt>
                                <dd>{{ $song->song_code }}.CCC</dd>
                            </div>
                            <div>
                                <dt>Status</dt>
                                <dd class="capitalize">{{ str_replace('_', ' ', $song->status) }}</dd>
                            </div>
                            <div>
                                <dt>Charts</dt>
                                <dd>{{ $chartCount }}</dd>
                            </div>
                            <div>
                                <dt>Instrument parts</dt>
                                <dd>{{ $instrumentPartCount }}</dd>
                            </div>
                            @if ($missingChartCount > 0)
                                <div>
                                    <dt>Missing charts</dt>
                                    <dd class="text-amber-700">{{ $missingChartCount }} part(s) without a chart</dd>
                                </div>
                            @endif
                            <div>
                                <dt>Last updated</dt>
                                <dd>{{ $song->updated_at?->timezone(config('app.timezone'))->format('j M Y, g:i A') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- 2. Musical Metadata --}}
                    <section id="musical-metadata" class="song-workspace-section">
                        <div class="song-workspace-section__head">
                            <h3 class="song-workspace-section__title">Musical Metadata</h3>
                            <a href="{{ route('songs.edit', $song) }}#musical-metadata" class="song-workspace-section__edit">Edit</a>
                        </div>
                        <dl class="song-workspace-dl">
                            <div>
                                <dt>BPM</dt>
                                <dd>{{ $song->bpm ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Time signature</dt>
                                <dd>{{ $song->timeSignature?->label ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Key</dt>
                                <dd>{{ $song->musicalKey?->label ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Mood</dt>
                                <dd>
                                    @if ($song->mood)
                                        <span class="inline-flex items-center gap-2">
                                            <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $song->mood->colour_hex }}"></span>
                                            {{ $song->mood->name }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Genre</dt>
                                <dd>{{ $song->genre ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Style</dt>
                                <dd>{{ $song->style ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Tempo feel</dt>
                                <dd>{{ $song->tempo_feel ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Count-in (bars)</dt>
                                <dd>{{ $song->count_in ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- 3. Director Brief --}}
                    <section id="director-brief" class="song-workspace-section">
                        <div class="song-workspace-section__head">
                            <h3 class="song-workspace-section__title">Director Brief</h3>
                            <a href="{{ route('songs.edit', $song) }}#director-brief" class="song-workspace-section__edit">Edit</a>
                        </div>
                        <div class="song-workspace-prose">
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-1">Song brief</h4>
                                <p class="text-sm text-gray-800 whitespace-pre-wrap break-words">{{ $song->director_notes ?: '—' }}</p>
                            </div>
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-1">Mood / intention</h4>
                                <p class="text-sm text-gray-800 whitespace-pre-wrap break-words">{{ $song->mood_intention ?: '—' }}</p>
                            </div>
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-1">Performance feel</h4>
                                <p class="text-sm text-gray-800 whitespace-pre-wrap break-words">{{ $song->performance_feel ?: '—' }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-1">Arrangement comments</h4>
                                <p class="text-sm text-gray-800 whitespace-pre-wrap break-words">{{ $song->arrangement_comments ?: '—' }}</p>
                            </div>
                        </div>
                    </section>

                    {{-- 4. Charts / Instrument Parts --}}
                    <section id="charts-parts" class="song-workspace-section">
                        <div class="song-workspace-section__head">
                            <h3 class="song-workspace-section__title">Charts / Instrument Parts</h3>
                        </div>

                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Instrument parts</h4>
                        @if ($song->songInstrumentParts->isEmpty())
                            <p class="text-sm text-gray-600 mb-6">No instrument parts assigned to this song.</p>
                        @else
                            <div class="overflow-x-auto mb-8">
                                <table class="min-w-full text-sm song-workspace-table">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Instrument part</th>
                                            <th class="text-left">Chart</th>
                                            <th class="text-left">Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($song->songInstrumentParts as $sip)
                                            <tr>
                                                <td>{{ $sip->instrumentPart->name }}</td>
                                                <td>{{ $sip->chart?->title ?? '—' }}</td>
                                                <td>
                                                    @if ($sip->chart)
                                                        <span class="text-green-700">Linked</span>
                                                    @else
                                                        <span class="text-amber-700">Missing chart</span>
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    <form method="POST" action="{{ route('songs.instrument-parts.destroy', [$song, $sip]) }}" onsubmit="return confirm('Remove this instrument part?');" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-xs text-red-600 hover:text-red-800">Remove</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($availableInstrumentParts->isNotEmpty())
                            <form method="POST" action="{{ route('songs.instrument-parts.store', $song) }}" class="flex flex-wrap items-end gap-3 mb-8">
                                @csrf
                                <div class="min-w-[12rem] flex-1">
                                    <x-input-label for="instrument_part_id" value="Assign instrument part" />
                                    <select id="instrument_part_id" name="instrument_part_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                                        <option value="">Select…</option>
                                        @foreach ($availableInstrumentParts as $part)
                                            <option value="{{ $part->id }}">{{ $part->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-primary-button type="submit">Assign</x-primary-button>
                            </form>
                        @endif

                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Charts</h4>
                        @if ($song->charts->isEmpty())
                            <p class="text-sm text-gray-600 mb-4">No charts uploaded for this song yet.</p>
                        @else
                            <div class="overflow-x-auto mb-6">
                                <table class="min-w-full text-sm song-workspace-table">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Title</th>
                                            <th class="text-left">Original filename</th>
                                            <th class="text-left">Storage reference</th>
                                            <th class="text-left">Linked parts</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($song->charts as $chart)
                                            <tr>
                                                <td class="font-medium">{{ $chart->title }}</td>
                                                <td class="break-all">{{ $chart->original_filename ?? '—' }}</td>
                                                <td class="break-all text-gray-600">{{ $chart->storage_reference }}</td>
                                                <td>
                                                    @php($linked = $song->songInstrumentParts->where('chart_id', $chart->id))
                                                    {{ $linked->isEmpty() ? '—' : $linked->map(fn ($sip) => $sip->instrumentPart->name)->join(', ') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @foreach ($song->charts as $chart)
                                @if ($song->songInstrumentParts->isNotEmpty())
                                    <div class="border border-gray-100 rounded-md p-4 mb-4">
                                        <p class="text-sm font-medium mb-2">Assign “{{ $chart->title }}”</p>
                                        <form method="POST" action="{{ route('charts.assign', $chart) }}">
                                            @csrf
                                            <div class="space-y-2 mb-3">
                                                @foreach ($song->songInstrumentParts as $sip)
                                                    <label class="flex items-center gap-2 text-sm">
                                                        <input type="checkbox" name="song_instrument_part_ids[]" value="{{ $sip->id }}"
                                                            @checked($sip->chart_id === $chart->id)>
                                                        <span>{{ $sip->instrumentPart->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <x-primary-button type="submit" class="text-xs">Update assignments</x-primary-button>
                                        </form>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        <form method="POST" action="{{ route('songs.charts.store', $song) }}" enctype="multipart/form-data" class="space-y-4 border-t border-gray-100 pt-6">
                            @csrf
                            <p class="text-sm font-medium text-gray-800">Upload chart</p>
                            <div class="grid gap-4 sm:grid-cols-2">
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
                            </div>
                            <x-primary-button type="submit">Upload Chart</x-primary-button>
                        </form>
                    </section>

                    {{-- 5. References --}}
                    <section id="references" class="song-workspace-section">
                        <div class="song-workspace-section__head">
                            <h3 class="song-workspace-section__title">References</h3>
                            <a href="{{ route('songs.edit', $song) }}#references" class="song-workspace-section__edit">Edit</a>
                        </div>
                        <dl class="song-workspace-dl">
                            <div>
                                <dt>Reference title</dt>
                                <dd>{{ $song->reference_title ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Reference URL</dt>
                                <dd>
                                    @if ($song->reference_url)
                                        <a href="{{ $song->reference_url }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-800 break-all">{{ $song->reference_url }}</a>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt>Reference notes</dt>
                                <dd class="whitespace-pre-wrap break-words">{{ $song->reference_notes ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- 6. Sync Readiness --}}
                    <section id="sync-readiness" class="song-workspace-section">
                        <div class="song-workspace-section__head">
                            <h3 class="song-workspace-section__title">Sync Readiness</h3>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            Audit identifiers for future Cloud Studio ↔ Live Stage synchronisation (ADR-001). Checkout, versioning, and conflict resolution are not implemented in this phase.
                        </p>
                        <dl class="song-workspace-dl">
                            <div>
                                <dt>Public ID</dt>
                                <dd class="font-mono text-xs break-all">{{ $song->public_id }}</dd>
                            </div>
                            <div>
                                <dt>Song code</dt>
                                <dd>{{ $song->song_code }}</dd>
                            </div>
                            <div>
                                <dt>Created</dt>
                                <dd>{{ $song->created_at?->timezone(config('app.timezone'))->format('j M Y, g:i A') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Updated</dt>
                                <dd>{{ $song->updated_at?->timezone(config('app.timezone'))->format('j M Y, g:i A') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- Cues (operational, secondary) --}}
                    <section id="cues" class="song-workspace-section">
                        <div class="song-workspace-section__head">
                            <h3 class="song-workspace-section__title">Cues</h3>
                        </div>
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
                        <form method="POST" action="{{ route('songs.cues.store', $song) }}" class="grid gap-4 sm:grid-cols-3 items-end">
                            @csrf
                            <div>
                                <x-input-label for="cue_number" value="Cue number (CCC)" />
                                <x-text-input id="cue_number" name="cue_number" type="text" maxlength="3" class="mt-1 block w-full" placeholder="000" required />
                            </div>
                            <div>
                                <x-input-label for="cue_name" value="Cue name" />
                                <x-text-input id="cue_name" name="name" type="text" class="mt-1 block w-full" required />
                            </div>
                            <x-primary-button type="submit">Add Cue</x-primary-button>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
