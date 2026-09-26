<div class="esb-studio__schedule-item-actions mb-4">
    <a href="{{ route('studio') }}" class="esb-studio__show-pill esb-studio__show-pill--action">Studio Home</a>
    <a href="{{ route('studio.performances.show', $performance) }}" class="esb-studio__show-pill esb-studio__show-pill--action">Performance</a>
    @if ($showTicketing ?? false)
        <a href="{{ route('studio.performances.ticketing.edit', $performance) }}" class="esb-studio__show-pill esb-studio__show-pill--action">Ticketing</a>
    @endif
    @if ($showDoor ?? false)
        <a href="{{ route('studio.performances.door', $performance) }}" class="esb-studio__show-pill esb-studio__show-pill--action">Door / Check-in</a>
    @endif
</div>
