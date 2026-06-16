@if (! empty($columns))
    <div class="vx32-routing-iem-grid" aria-label="Monitor and return buses">
        @foreach ($columns as $column)
            <ul class="vx32-routing-iem-grid__column">
                @foreach ($column as $bus)
                    <li class="vx32-routing-iem-grid__line">
                        <span class="vx32-routing-iem-grid__number">{{ $bus['number'] }}</span>
                        <span class="vx32-routing-iem-grid__name">{{ $bus['name'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </div>
@endif
