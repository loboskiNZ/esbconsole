@php
    $actions = $routingBottom['configuration_actions'];
    $advanced = $routingBottom['advanced'];
@endphp

<section class="vx32-routing-bottom" aria-labelledby="routing-bottom-title">
    <h2 id="routing-bottom-title" class="vx32-routing-bottom__sr-title">Routing Workflow</h2>

    <div class="vx32-routing-bottom__grid">
        <article class="vx32-routing-bottom__panel vx32-routing-bottom__panel--actions">
            <h3 class="vx32-routing-bottom__panel-title">{{ $actions['title'] }}</h3>

            <div class="vx32-routing-bottom__workflow">
                @foreach ($actions['steps'] as $index => $step)
                    @if ($index > 0)
                        <span class="vx32-routing-bottom__workflow-arrow" aria-hidden="true">→</span>
                    @endif

                    @if ($step['url'] !== null && $step['state'] === 'available')
                        <a
                            href="{{ $step['url'] }}"
                            @class([
                                'vx32-routing-bottom__step',
                                'vx32-routing-bottom__step--available',
                            ])
                        >
                            @include('console._routing-bottom-step', ['step' => $step])
                        </a>
                    @else
                        <div @class([
                            'vx32-routing-bottom__step',
                            'vx32-routing-bottom__step--available' => $step['state'] === 'available',
                            'vx32-routing-bottom__step--not-available' => $step['state'] === 'not_available',
                            'vx32-routing-bottom__step--coming-later' => $step['state'] === 'coming_later',
                        ])>
                            @include('console._routing-bottom-step', ['step' => $step])
                        </div>
                    @endif
                @endforeach
            </div>
        </article>

        <article class="vx32-routing-bottom__panel vx32-routing-bottom__panel--advanced">
            <h3 class="vx32-routing-bottom__panel-title">{{ $advanced['title'] }}</h3>
            <p class="vx32-routing-bottom__advanced-desc">{{ $advanced['description'] }}</p>

            <ul class="vx32-routing-bottom__advanced-categories">
                @foreach ($advanced['categories'] as $category)
                    <li class="vx32-routing-bottom__advanced-chip" title="{{ $category['status_label'] ?? '' }}">
                        {{ $category['label'] }}@if (! empty($category['status_label'])) · {{ $category['status_label'] }}@endif
                    </li>
                @endforeach
            </ul>

            <button
                type="button"
                class="vx32-routing-bottom__advanced-action"
                disabled
                title="{{ $advanced['status_label'] }}"
            >{{ $advanced['action_label'] }}</button>
        </article>
    </div>
</section>
