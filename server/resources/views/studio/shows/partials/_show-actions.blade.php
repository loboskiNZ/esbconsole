@props([
    'show',
    'archived' => false,
])

<div class="esb-studio__show-pills">
    <a
        href="{{ route('studio.shows.edit', $show) }}"
        class="esb-studio__show-pill esb-studio__show-pill--action"
    >
        Edit
    </a>

    @if ($archived)
        <form
            method="POST"
            action="{{ route('studio.shows.restore', $show) }}"
            class="esb-studio__show-pill-form"
        >
            @csrf
            @method('PATCH')
            <button type="submit" class="esb-studio__show-pill esb-studio__show-pill--action">
                Restore
            </button>
        </form>
    @else
        <form
            method="POST"
            action="{{ route('studio.shows.archive', $show) }}"
            class="esb-studio__show-pill-form"
            onsubmit="return confirm('Archive this show? It will be hidden from the dashboard and main shows list.');"
        >
            @csrf
            @method('PATCH')
            <button type="submit" class="esb-studio__show-pill esb-studio__show-pill--action">
                Archive
            </button>
        </form>
    @endif
</div>
