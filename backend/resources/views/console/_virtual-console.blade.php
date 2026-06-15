<div class="space-y-6">
    @if (! empty($channels))
        <div>
            <h4 class="font-semibold text-sm mb-2">Input Channels</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Name</th>
                            <th class="px-3 py-2 text-left">Fader</th>
                            <th class="px-3 py-2 text-left">Mute</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($channels as $channel)
                            <tr>
                                <td class="px-3 py-2">{{ $channel['index'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $channel['name'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $channel['fader'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ ! empty($channel['mute']) ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (! empty($buses))
        <div>
            <h4 class="font-semibold text-sm mb-2">Buses</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Name</th>
                            <th class="px-3 py-2 text-left">Fader</th>
                            <th class="px-3 py-2 text-left">Mute</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($buses as $bus)
                            <tr>
                                <td class="px-3 py-2">{{ $bus['index'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $bus['name'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $bus['fader'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ ! empty($bus['mute']) ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (! empty($dcas))
        <div>
            <h4 class="font-semibold text-sm mb-2">DCAs</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Name</th>
                            <th class="px-3 py-2 text-left">Fader</th>
                            <th class="px-3 py-2 text-left">Mute</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($dcas as $dca)
                            <tr>
                                <td class="px-3 py-2">{{ $dca['index'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $dca['name'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $dca['fader'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ ! empty($dca['mute']) ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (! empty($matrices))
        <div>
            <h4 class="font-semibold text-sm mb-2">Matrices</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Name</th>
                            <th class="px-3 py-2 text-left">Fader</th>
                            <th class="px-3 py-2 text-left">Mute</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($matrices as $matrix)
                            <tr>
                                <td class="px-3 py-2">{{ $matrix['index'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $matrix['name'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $matrix['fader'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ ! empty($matrix['mute']) ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (! empty($fx))
        <div>
            <h4 class="font-semibold text-sm mb-2">FX Slots</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Slot</th>
                            <th class="px-3 py-2 text-left">Name</th>
                            <th class="px-3 py-2 text-left">Type</th>
                            <th class="px-3 py-2 text-left">Enabled</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($fx as $slot)
                            <tr>
                                <td class="px-3 py-2">{{ $slot['slot'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $slot['name'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $slot['type'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ ! empty($slot['enabled']) ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
