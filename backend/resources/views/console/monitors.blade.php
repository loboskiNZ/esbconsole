@php
    use App\Services\X32\X32FaderScale;

    $header = $monitorsWorkspace['header'];
    $sidebar = $monitorsWorkspace['sidebar'];
    $activeBusNumber = $monitorsWorkspace['active_bus_number'];
    $activeBusName = $monitorsWorkspace['active_bus_name'];
    $selectedChannelNumber = $monitorsWorkspace['selected_channel_number'];
    $eq = $monitorsWorkspace['eq'];
    $channels = $monitorsWorkspace['channels'];
    $channelSettings = $monitorsWorkspace['channel_settings'];
    $groupControl = $monitorsWorkspace['group_control'];
    $busMaster = $monitorsWorkspace['bus_master'];
    $faderScaleMarks = X32FaderScale::consoleScaleMarks();
    $faderUnityPct = X32FaderScale::unityMarkPercent();
@endphp

<x-console-layout>
    <div class="vx32-console">
        @include('console._console-header', [
            'show' => $show,
            'consoleType' => $consoleType,
            'workspaceMode' => $workspaceMode,
            'summary' => $summary,
            'activeTab' => 'monitor',
        ])

        <div class="vx32-routing-workspace vx32-monitors-workspace">
            <header class="vx32-routing-workspace__header">
                <div class="vx32-routing-workspace__header-left">
                    <span class="vx32-routing-workspace__context">{{ $header['context'] }}</span>
                    <div class="vx32-routing-workspace__title-row">
                        <h1 class="vx32-routing-workspace__title">{{ $header['title'] }}</h1>
                        <span @class([
                            'vx32-routing-workspace__routing-state',
                            'vx32-routing-workspace__routing-state--suggested' => ($header['status_state'] ?? '') === 'suggested',
                            'vx32-routing-workspace__routing-state--learned' => ($header['status_state'] ?? '') === 'learned',
                            'vx32-routing-workspace__routing-state--not-learned' => ($header['status_state'] ?? '') === 'not-learned',
                        ])>
                            {{ strtoupper($header['status_label']) }}
                        </span>
                    </div>
                </div>
            </header>

            <div class="vx32-monitors-workspace__body">
                <div class="vx32-monitors-main vx32-monitors-main--eq-collapsed" data-monitors-main>
                    <div class="vx32-monitors-main__stack">
                    <section class="vx32-routing-detail__panel vx32-monitors-eq is-collapsed" aria-labelledby="monitors-eq-title" data-eq-panel>
                        <header class="vx32-monitors-eq__head">
                            <div class="vx32-monitors-eq__head-bar">
                                <h2 id="monitors-eq-title" class="vx32-routing-detail__panel-title">{{ $eq['title'] }}</h2>
                                <button
                                    type="button"
                                    class="vx32-monitors-eq__panel-toggle"
                                    data-eq-panel-toggle
                                    aria-expanded="false"
                                    aria-controls="monitors-eq-collapsible"
                                >Show</button>
                            </div>

                            <div class="vx32-monitors-eq__head-details">
                                <div class="vx32-monitors-eq__head-main">
                                    <p class="vx32-monitors-eq__scope">{{ $eq['scope_hint'] }}</p>
                                    <p class="vx32-monitors-eq__layout-note">{{ $eq['layout_note'] }}</p>
                                </div>
                                <div class="vx32-monitors-eq__head-actions">
                                    <span @class([
                                        'vx32-monitors-eq__status-badge',
                                        'vx32-monitors-eq__status-badge--learned' => $eq['status_badge']['state'] === 'learned',
                                        'vx32-monitors-eq__status-badge--placeholder' => $eq['status_badge']['state'] === 'placeholder',
                                    ])>{{ $eq['status_badge']['label'] }}</span>
                                    <button
                                        type="button"
                                        class="vx32-monitors-eq__toggle {{ $eq['enabled'] ? 'is-on' : '' }}"
                                        disabled
                                        title="Display only — bus master EQ bypass"
                                        aria-label="Bus master EQ bypass · {{ $eq['enabled_display'] }}"
                                    >{{ $eq['enabled_display'] }}</button>
                                </div>
                            </div>
                        </header>

                        <div id="monitors-eq-collapsible" class="vx32-monitors-eq__collapsible" data-eq-panel-collapsible>
                        @if ($eq['placeholder_notice'])
                            <p class="vx32-monitors-eq__placeholder-notice">{{ $eq['placeholder_notice'] }}</p>
                        @endif

                        <div class="vx32-monitors-eq__body" data-eq-workspace>
                            <div class="vx32-monitors-eq__graph-wrap" data-eq-graph-full-width="true">
                                <div class="vx32-monitors-eq__graph-shell">
                                    <div class="vx32-monitors-eq__graph-y-axis" aria-hidden="true">
                                        @foreach ($eq['graph']['gain_labels'] as $gainLabel)
                                            <span>{{ $gainLabel }}</span>
                                        @endforeach
                                    </div>

                                    <div class="vx32-monitors-eq__graph-plot">
                                        <svg
                                            class="vx32-monitors-eq__graph"
                                            viewBox="0 0 640 180"
                                            preserveAspectRatio="none"
                                            role="img"
                                            aria-label="Bus master EQ visual approximation"
                                            data-eq-graph
                                            data-eq-gain-min="{{ $eq['graph']['gain_min'] }}"
                                            data-eq-gain-max="{{ $eq['graph']['gain_max'] }}"
                                            data-eq-freq-min="{{ $eq['graph']['freq_min'] }}"
                                            data-eq-freq-max="{{ $eq['graph']['freq_max'] }}"
                                        >
                                            <rect x="0" y="0" width="640" height="180" fill="rgb(12 12 14)" />
                                            @foreach ($eq['graph']['gain_labels'] as $index => $gainLabel)
                                                @php
                                                    $y = [30, 90, 150][$index] ?? 90;
                                                @endphp
                                                <line x1="0" y1="{{ $y }}" x2="640" y2="{{ $y }}" stroke="rgb(39 39 42)" stroke-width="1" />
                                            @endforeach
                                            @foreach ($eq['graph']['frequency_axis'] as $freq)
                                                @php
                                                    $ratio = log10($freq / 20) / log10(20000 / 20);
                                                    $x = $ratio * 640;
                                                @endphp
                                                <line x1="{{ $x }}" y1="8" x2="{{ $x }}" y2="156" stroke="rgb(39 39 42)" stroke-width="1" stroke-dasharray="3 4" opacity="0.55" />
                                            @endforeach
                                            <path data-eq-curve d="{{ $eq['graph']['path_d'] }}" fill="none" stroke="rgb(56 189 248)" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                                            @foreach ($eq['graph']['band_nodes'] as $node)
                                                <circle
                                                    cx="{{ $node['x'] }}"
                                                    cy="{{ $node['y'] }}"
                                                    r="7"
                                                    class="vx32-monitors-eq__handle vx32-monitors-eq__handle--{{ $node['color'] }}"
                                                    data-eq-handle
                                                    data-eq-band="{{ $node['number'] }}"
                                                    data-eq-gain-draggable="{{ $node['gain_draggable'] ? 'true' : 'false' }}"
                                                    vector-effect="non-scaling-stroke"
                                                />
                                            @endforeach
                                        </svg>

                                        <div class="vx32-monitors-eq__graph-x-axis" aria-hidden="true">
                                            @foreach ($eq['graph']['frequency_axis'] as $index => $freq)
                                                @php
                                                    $ratio = log10($freq / 20) / log10(20000 / 20);
                                                    $leftPct = $ratio * 100;
                                                @endphp
                                                <span style="left: {{ $leftPct }}%;">{{ $eq['graph']['frequency_labels'][$index] }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <p class="vx32-monitors-eq__graph-note">{{ $eq['graph']['disclaimer'] }}</p>
                            </div>

                            <div class="vx32-monitors-eq__bands-row" data-eq-bands-below-graph="true">
                                @foreach ($eq['bands'] as $band)
                                    <article
                                        @class([
                                            'vx32-monitors-eq__band-strip',
                                            'vx32-monitors-eq__band-strip--placeholder' => $band['is_placeholder'],
                                            'vx32-monitors-eq__band-strip--'.$band['color'],
                                        ])
                                        data-eq-band-strip
                                        data-eq-band="{{ $band['number'] }}"
                                    >
                                        <div class="vx32-monitors-eq__band-strip-head">
                                            <span class="vx32-monitors-eq__band-strip-num">{{ $band['number'] }}</span>
                                            <span class="vx32-monitors-eq__band-strip-name">{{ $band['short_name'] }}</span>
                                        </div>

                                        <label class="vx32-monitors-eq__field vx32-monitors-eq__field--mode">
                                            <span class="vx32-monitors-eq__field-label">Mode</span>
                                            <select class="vx32-monitors-eq__field-select" data-eq-mode-select title="Bus EQ mode">
                                                @foreach ($band['mode_options'] as $modeOption)
                                                    <option value="{{ $modeOption }}" @selected($modeOption === $band['mode'])>{{ $modeOption }}</option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <label class="vx32-monitors-eq__field" data-eq-field="frequency" @if (! $band['frequency_visible']) hidden @endif>
                                            <span class="vx32-monitors-eq__field-label">Freq</span>
                                            <input type="text" class="vx32-monitors-eq__field-input" data-eq-input="frequency" value="{{ $band['frequency_input'] }}" inputmode="decimal" spellcheck="false">
                                        </label>

                                        <label class="vx32-monitors-eq__field" data-eq-field="gain" @if (! $band['gain_visible']) hidden @endif>
                                            <span class="vx32-monitors-eq__field-label">Gain</span>
                                            <input type="text" class="vx32-monitors-eq__field-input" data-eq-input="gain" value="{{ $band['gain_input'] }}" inputmode="decimal" spellcheck="false">
                                        </label>

                                        <label class="vx32-monitors-eq__field" data-eq-field="q" @if (! $band['q_visible']) hidden @endif>
                                            <span class="vx32-monitors-eq__field-label">Q</span>
                                            <input type="text" class="vx32-monitors-eq__field-input" data-eq-input="q" value="{{ $band['q_input'] }}" inputmode="decimal" spellcheck="false">
                                        </label>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                        </div>
                    </section>

                    <section class="vx32-routing-detail__panel vx32-monitors-channels" aria-labelledby="monitors-channels-title" data-monitors-channels>
                        <header class="vx32-monitors-channels__head">
                            <div class="vx32-monitors-channels__head-row">
                                <h2 id="monitors-channels-title" class="vx32-routing-detail__panel-title">{{ $channels['title'] }}</h2>
                                <div class="vx32-monitors-channels__view-toggle" role="group" aria-label="Channel fader view">
                                    <button
                                        type="button"
                                        class="vx32-monitors-channels__view-btn is-active"
                                        data-channels-view="all"
                                        aria-pressed="true"
                                    >{{ $groupControl['all_channels_view_label'] }}</button>
                                    <button
                                        type="button"
                                        class="vx32-monitors-channels__view-btn"
                                        data-channels-view="group"
                                        aria-pressed="false"
                                    >{{ $groupControl['group_view_label'] }}</button>
                                </div>
                            </div>
                        </header>

                        <div class="vx32-monitors-channels__strips" data-channel-strips>
                            @foreach ($groupControl['groups'] as $group)
                                <article
                                    class="vx32-monitors-strip vx32-monitors-group-strip vx32-monitors-group-strip--{{ $group['key'] }}"
                                    data-group-strip
                                    data-group-key="{{ $group['key'] }}"
                                    data-group-label="{{ $group['label'] }}"
                                    hidden
                                >
                                    <span class="vx32-monitors-strip__num" aria-hidden="true">G</span>
                                    <span class="vx32-monitors-strip__name">{{ $group['label'] }}</span>
                                    <button
                                        type="button"
                                        class="vx32-monitors-group-strip__clear"
                                        data-group-clear
                                        title="Clear all channels from {{ $group['label'] }}"
                                    >Clear</button>
                                    @include('console._monitors-fader-track', [
                                        'handleBottomPct' => 50,
                                        'trackAttributes' => ['data-group-fader-track' => ''],
                                        'handleAttributes' => ['data-group-fader-handle' => ''],
                                    ])
                                    <span class="vx32-monitors-strip__level" data-group-fader-level>0.0</span>
                                </article>
                            @endforeach
                            @foreach ($channels['strips'] as $strip)
                                @php
                                    $handleBottomPct = $strip['level_db'] !== null
                                        ? X32FaderScale::dbMarkPercent((float) $strip['level_db'])
                                        : $faderUnityPct;
                                    $channelUrl = route('shows.console.bus.layout', [
                                        $show,
                                        $activeBusNumber,
                                        'channel' => $strip['number'],
                                    ]);
                                @endphp
                                <article
                                    @class([
                                        'vx32-monitors-strip',
                                        'vx32-monitors-channel-strip',
                                        'is-color-default' => ! $strip['color_learned'],
                                        'is-selected' => $selectedChannelNumber === $strip['number'],
                                    ])
                                    data-channel-strip
                                    data-channel="{{ $strip['number'] }}"
                                    data-group-pick-target
                                    data-level-db="{{ $strip['send_learned'] && is_numeric($strip['level_db'] ?? null) ? number_format((float) $strip['level_db'], 2, '.', '') : '' }}"
                                    data-channel-color-index="{{ $strip['color_index'] }}"
                                    style="--channel-color: {{ $strip['color_css'] }}; --channel-color-text: {{ $strip['color_text'] }};"
                                    title="{{ $strip['color_label'] }} · {{ $strip['display_name'] }}"
                                >
                                    <span class="vx32-monitors-strip__num">{{ $strip['number'] }}</span>
                                    <a href="{{ $channelUrl }}" class="vx32-monitors-strip__name">{{ $strip['display_name'] }}</a>
                                    <span class="vx32-monitors-strip__group-badge" data-group-control-badge hidden></span>
                                    <button
                                        type="button"
                                        class="vx32-monitors-strip__mute"
                                        disabled
                                        title="{{ $strip['mute_scope_label'] }}"
                                        aria-label="{{ $strip['mute_scope_label'] }}"
                                        {{ $strip['mute'] ? 'data-muted' : '' }}
                                    >M</button>
                                    @include('console._monitors-fader-track', [
                                        'handleBottomPct' => $handleBottomPct,
                                        'trackAttributes' => ['data-channel-fader-track' => ''],
                                        'handleAttributes' => ['data-channel-fader-handle' => ''],
                                    ])
                                    <span class="vx32-monitors-strip__level" data-channel-fader-level>{{ $strip['level_display'] }}</span>
                                </article>
                            @endforeach
                            @php
                                $masterHandleBottomPct = $busMaster['level_db'] !== null
                                    ? X32FaderScale::dbMarkPercent((float) $busMaster['level_db'])
                                    : $faderUnityPct;
                            @endphp
                            <article
                                class="vx32-monitors-strip vx32-monitors-bus-master-strip"
                                data-bus-master-strip
                                aria-label="{{ $busMaster['title'] }} · {{ $busMaster['bus_name'] }}"
                                title="{{ $busMaster['scope_hint'] }}"
                            >
                                <span class="vx32-monitors-strip__num">{{ $busMaster['bus_number'] }}</span>
                                <span class="vx32-monitors-strip__name">{{ $busMaster['title'] }}</span>
                                <button
                                    type="button"
                                    class="vx32-monitors-strip__mute"
                                    disabled
                                    title="Monitor bus mute · {{ $busMaster['bus_name'] }}"
                                    aria-label="Monitor bus mute · {{ $busMaster['bus_name'] }}"
                                    {{ $busMaster['mute'] ? 'data-muted' : '' }}
                                >M</button>
                                @include('console._monitors-fader-track', [
                                    'handleBottomPct' => $masterHandleBottomPct,
                                    'trackAttributes' => ['data-bus-master-fader-track' => ''],
                                    'handleAttributes' => ['data-bus-master-fader-handle' => ''],
                                ])
                                <span class="vx32-monitors-strip__level" data-bus-master-fader-level>{{ $busMaster['level_display'] }}</span>
                                <span class="vx32-monitors-bus-master-strip__bus-name">{{ $busMaster['bus_name'] }}</span>
                            </article>
                        </div>
                    </section>
                    </div>

                    <aside class="vx32-routing-detail__panel vx32-monitors-sidebar" aria-label="Monitor buses">
                        <details class="vx32-monitors-bus-picker" data-monitors-collapsible open>
                            <summary class="vx32-monitors-bus-picker__summary">
                                <span class="vx32-monitors-bus-picker__summary-label">{{ $sidebar['title'] }}</span>
                                <span class="vx32-monitors-bus-picker__summary-value">{{ $activeBusName }}</span>
                                <span class="vx32-monitors-bus-picker__summary-chevron" aria-hidden="true"></span>
                            </summary>
                            <div class="vx32-monitors-bus-picker__panel">
                                <header class="vx32-monitors-sidebar__head">
                                    <h2 class="vx32-monitors-sidebar__title">{{ $sidebar['title'] }}</h2>
                                    <span class="vx32-monitors-sidebar__count">{{ $sidebar['available_count'] }} available</span>
                                </header>

                                <ul class="vx32-monitors-sidebar__list">
                                    @foreach ($sidebar['buses'] as $bus)
                                        <li>
                                            <a
                                                href="{{ route('shows.console.bus.layout', [$show, $bus['number']]) }}"
                                                @class([
                                                    'vx32-monitors-sidebar__item',
                                                    'is-active' => $bus['number'] === $activeBusNumber,
                                                ])
                                            >
                                                <span class="vx32-monitors-sidebar__item-num">{{ $bus['number'] }}</span>
                                                <span class="vx32-monitors-sidebar__item-name" title="{{ $bus['display_name'] }}">{{ $bus['display_name'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>

                                <p class="vx32-monitors-sidebar__footer">{{ $sidebar['footer_note'] }}</p>
                            </div>
                        </details>
                    </aside>

                    <div class="vx32-monitors-bottom">
                        <section class="vx32-routing-detail__panel vx32-monitors-channel-settings" aria-labelledby="monitors-channel-settings-title">
                            <h2 id="monitors-channel-settings-title" class="vx32-routing-detail__panel-title">{{ $channelSettings['title'] }}</h2>

                            @if ($channelSettings['empty'])
                                <p class="vx32-monitors-channel-settings__empty">{{ $channelSettings['empty_message'] }}</p>
                            @else
                                <dl class="vx32-monitors-channel-settings__rows">
                                    @foreach ($channelSettings['rows'] as $row)
                                        <div class="vx32-monitors-channel-settings__row">
                                            <dt>{{ $row['label'] }}</dt>
                                            <dd>{{ $row['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </section>

                        <section class="vx32-routing-detail__panel vx32-monitors-groups" aria-labelledby="monitors-groups-title" data-monitors-group-control>
                            <details class="vx32-monitors-groups-menu" data-monitors-collapsible open>
                                <summary class="vx32-monitors-groups-menu__summary">
                                    <span class="vx32-monitors-groups-menu__summary-label">{{ $groupControl['title'] }}</span>
                                    <span class="vx32-monitors-groups-menu__summary-hint" data-group-menu-hint>No channels selected</span>
                                    <span class="vx32-monitors-groups-menu__summary-chevron" aria-hidden="true"></span>
                                </summary>
                                <div class="vx32-monitors-groups-menu__panel">
                            <h2 id="monitors-groups-title" class="vx32-routing-detail__panel-title vx32-monitors-groups__desktop-title">{{ $groupControl['title'] }}</h2>
                            <p class="vx32-monitors-groups__scaffold">{{ $groupControl['scaffold_notice'] }}</p>
                            <p class="vx32-monitors-groups__selection-status" data-group-selection-status>No channels selected</p>
                            <div class="vx32-monitors-groups__actions">
                                <button type="button" class="vx32-monitors-groups__clear-pick" data-group-clear-pick hidden>{{ $groupControl['clear_selection_label'] }}</button>
                                <button type="button" class="vx32-monitors-groups__remove-from" data-group-remove-from hidden>{{ $groupControl['remove_from_group_label'] }}</button>
                                <button type="button" class="vx32-monitors-groups__clear-group" data-group-clear-active hidden>{{ $groupControl['clear_group_label'] }}</button>
                            </div>
                            <div class="vx32-monitors-groups__pills">
                                <button
                                    type="button"
                                    class="vx32-monitors-groups__pill vx32-monitors-groups__pill--all is-active"
                                    data-group-select
                                    data-group-key=""
                                    data-group-label="{{ $groupControl['all_channels_label'] }}"
                                    aria-pressed="true"
                                >{{ $groupControl['all_channels_label'] }}</button>
                                @foreach ($groupControl['groups'] as $group)
                                    <button
                                        type="button"
                                        class="vx32-monitors-groups__pill vx32-monitors-groups__pill--{{ $group['key'] }}"
                                        data-group-select
                                        data-group-key="{{ $group['key'] }}"
                                        data-group-label="{{ $group['label'] }}"
                                        data-group-channels=""
                                        aria-pressed="false"
                                        title="Assign selected channels to {{ $group['label'] }}"
                                    >{{ $group['label'] }}</button>
                                @endforeach
                            </div>
                                </div>
                            </details>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-console-layout>
