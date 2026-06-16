@props([
    'show',
    'workspaceMode',
    'summary',
    'learnedAtDisplay' => null,
])

<div class="vx32-subbar vx32-subbar--configuration">
    <div class="vx32-subbar__meta vx32-subbar__meta--wide">
        <span>Scene {{ $summary['scene_number'] ?? '—' }}</span>
        @if ($workspaceMode === 'preview')
            <span class="vx32-subbar__badge">Unsaved preview</span>
        @else
            <span class="vx32-subbar__badge vx32-subbar__badge--saved">Active</span>
        @endif
    </div>

    <div class="vx32-configuration-subbar__actions">
        <span class="vx32-configuration-subbar__context">
            {{ $workspaceMode === 'preview' ? 'Previewing learned configuration' : 'Saved configuration baseline' }}
        </span>
        @if ($learnedAtDisplay)
            <span class="vx32-configuration-subbar__timestamp">Learned {{ $learnedAtDisplay }}</span>
        @endif
        <a href="{{ route('shows.console.learn', $show) }}" class="vx32-configuration-subbar__learn-again">
            <svg class="vx32-configuration-subbar__learn-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
            </svg>
            Learn Again
        </a>
    </div>
</div>
