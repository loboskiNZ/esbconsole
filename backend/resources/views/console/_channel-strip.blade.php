@props(['strip', 'labelPrefix' => 'CH'])

<div class="flex flex-col items-center w-16 shrink-0 rounded-lg bg-slate-800 border border-slate-700 p-2 text-slate-100">
    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $labelPrefix }} {{ str_pad((string) ($strip['index'] ?? ''), 2, '0', STR_PAD_LEFT) }}</span>
    <span class="text-[10px] text-center leading-tight h-8 overflow-hidden w-full truncate" title="{{ $strip['name'] ?? '' }}">{{ $strip['name'] ?? '—' }}</span>
    <div class="relative flex-1 w-6 h-24 my-2 bg-slate-900 rounded-sm border border-slate-600">
        @php $level = min(100, max(0, (float) ($strip['fader'] ?? 0) * 100)); @endphp
        <div class="absolute bottom-0 left-0 right-0 bg-emerald-500 rounded-sm transition-all" style="height: {{ $level }}%"></div>
    </div>
    <span class="text-[10px] tabular-nums text-slate-300">{{ number_format((float) ($strip['fader'] ?? 0), 2) }}</span>
    @if (! empty($strip['mute']))
        <span class="mt-1 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-red-600 text-white">Mute</span>
    @else
        <span class="mt-1 text-[9px] uppercase text-slate-500">On</span>
    @endif
</div>
