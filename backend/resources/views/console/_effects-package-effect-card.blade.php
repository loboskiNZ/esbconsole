@php
    use App\Enums\EffectReturnDestination;
    use App\Enums\EffectRoutingMode;
    use App\Enums\EffectRoutingTargetSection;

    $x32Effect = $item->x32Effect;
    $definition = $item->effectDefinition;
    $displayName = $x32Effect?->displayName() ?? $definition?->name ?? 'Unknown Effect';
    $effectCode = $x32Effect?->effect_code ?? $definition?->x32_algorithm_code;
    $algorithmId = $x32Effect?->x32_algorithm_id ?? $definition?->x32_algorithm_id;
    $slotGroupLabel = $x32Effect?->slotGroupLabel() ?? $definition?->x32_slot_group?->label();
    $x32EffectName = $x32Effect?->effect_name ?? $definition?->name;
    $parameters = $item->parameters->sortBy('parameter_number')->values();
    $slotGroup = $x32Effect?->x32_slot_group ?? $definition?->x32_slot_group;
    $allowedSlots = $slotGroup?->allowedSlotNumbers() ?? range(1, 8);
    $allowedHelper = $slotGroup?->allowedSlotsHelper() ?? 'FX1–FX8';
    $currentSlot = $item->preferred_slot_number;
    $slotUpdateUrl = route('shows.console.effects.update-package-item-slot', [$show, $item]);
    $routingPlanUpdateUrl = route('shows.console.effects.update-package-item-routing-plan', [$show, $item]);
    $deployUrl = route('shows.console.effects.deploy-package-item', [$show, $item]);
    $deployLiveAvailable = ($effectDeployControl['available'] ?? false) === true;
    $deployInitialStatus = $currentSlot === null ? 'not_allocated' : 'ready';
    $deployInitialLabel = match ($deployInitialStatus) {
        'not_allocated' => 'Not allocated',
        default => 'Ready to deploy',
    };
    $deployButtonEnabled = $currentSlot !== null && $deployLiveAvailable;
    $allocationState = $currentSlot ? 'allocated' : 'unallocated';
    $suggestedRoutingPlan = $routingPlanSuggester->suggest($x32Effect);
    $routingMode = $item->routing_mode ?? $suggestedRoutingPlan['routing_mode'];
    $returnDestination = $item->return_destination ?? $suggestedRoutingPlan['return_destination'];
    $defaultReturnLevel = $item->default_return_level ?? $suggestedRoutingPlan['default_return_level'];
    $selectedTargetSections = $item->resolvedTargetSectionValues();
    if ($selectedTargetSections === []) {
        $selectedTargetSections = collect($suggestedRoutingPlan['target_sections'])
            ->map(fn (EffectRoutingTargetSection $section) => $section->value)
            ->all();
    }
    $targetSectionsSummary = $item->targetSections->isNotEmpty() || ($item->target_section !== null && $item->target_section !== EffectRoutingTargetSection::NotConfigured)
        ? $item->routingTargetSectionsSummary()
        : (collect($suggestedRoutingPlan['target_sections'])->isEmpty()
            ? 'Not selected'
            : collect($suggestedRoutingPlan['target_sections'])->map(fn (EffectRoutingTargetSection $section) => $section->label())->implode(', '));
    $defaultReturnLevelInput = $defaultReturnLevel !== null
        ? rtrim(rtrim(number_format((float) $defaultReturnLevel, 2, '.', ''), '0'), '.')
        : '';
@endphp

<div
    class="vx32-routing-detail__input-card vx32-routing-detail__input-card--effect"
    data-effect-package-item-card
    data-package-id="{{ $package->id }}"
    data-item-id="{{ $item->id }}"
>
    <header class="vx32-routing-detail__input-card-head">
        <div class="vx32-effects-workspace__effect-card-headings">
            <h4 class="vx32-routing-detail__input-card-title">{{ $displayName }}</h4>
            <p class="vx32-effects-workspace__effect-card-technical">
                @if ($effectCode)
                    {{ $effectCode }}
                @endif
                @if ($algorithmId !== null)
                    · Algorithm {{ $algorithmId }}
                @endif
                @if ($slotGroupLabel)
                    · {{ $slotGroupLabel }}
                @endif
                @if ($x32EffectName)
                    · X32: {{ $x32EffectName }}
                @endif
            </p>
        </div>
        <span class="vx32-routing-detail__input-routing-pill vx32-routing-detail__input-routing-pill--routed">Parameters:</span>
    </header>

    <div
        class="vx32-effects-workspace__slot-allocation vx32-effects-workspace__slot-allocation--{{ $allocationState }}"
        data-effect-slot-allocation
    >
        <label class="vx32-effects-workspace__slot-allocation-label">
            <span class="vx32-effects-workspace__slot-allocation-title">Slot allocation</span>
            <select
                class="vx32-effects-workspace__slot-allocation-select"
                data-effect-slot-input
                data-update-url="{{ $slotUpdateUrl }}"
                data-package-id="{{ $package->id }}"
                data-item-id="{{ $item->id }}"
                title="Slot allocation for {{ $displayName }}"
            >
                <option value="" @selected($currentSlot === null)>Not allocated</option>
                @foreach ($allowedSlots as $slot)
                    @php
                        $slotConflict = $slotAvailability->reasonForSlot($item, $slot);
                        $isUnavailable = $slotConflict !== null;
                        $isPermanentReservation = ($slotConflict['reason'] ?? null) === 'permanent_reservation';
                    @endphp
                    <option
                        value="{{ $slot }}"
                        @selected($currentSlot === $slot)
                        @disabled($isUnavailable)
                        @if ($isPermanentReservation) data-permanent-reserved="1" @endif
                        @if ($isUnavailable && ! $isPermanentReservation) data-same-package-lock="1" @endif
                        @if ($isUnavailable) title="{{ $slotConflict['message'] }}" @endif
                    >
                        FX{{ $slot }}@if ($isUnavailable) ({{ $isPermanentReservation ? 'reserved' : 'in use' }})@endif
                    </option>
                @endforeach
            </select>
        </label>

        <p class="vx32-effects-workspace__slot-allocation-helper">Allowed: {{ $allowedHelper }}</p>

        <p
            class="vx32-effects-workspace__slot-allocation-pill"
            data-effect-slot-pill
            @if ($currentSlot === null) hidden @endif
        >
            Allocated: FX<span data-effect-slot-pill-value>{{ $currentSlot }}</span>
        </p>

        <p
            class="vx32-effects-workspace__slot-allocation-summary"
            data-effect-slot-summary
            @if ($currentSlot === null) hidden @endif
        >
            Slot Allocation: FX<span data-effect-slot-summary-value>{{ $currentSlot }}</span>
        </p>

        <p class="vx32-effects-workspace__slot-allocation-error" data-effect-slot-error hidden></p>
    </div>

    <section
        class="vx32-effects-workspace__routing-plan"
        data-effect-routing-plan
        data-update-url="{{ $routingPlanUpdateUrl }}"
    >
        <h5 class="vx32-effects-workspace__routing-plan-title">Routing Plan</h5>

        <div class="vx32-effects-workspace__routing-plan-fields">
            <div class="vx32-effects-workspace__routing-plan-row">
                <label class="vx32-effects-workspace__routing-plan-field">
                    <span class="vx32-effects-workspace__routing-plan-label">Routing Mode</span>
                    <select class="vx32-effects-workspace__routing-plan-input" data-routing-plan-field="routing_mode">
                        @foreach (EffectRoutingMode::cases() as $option)
                            <option value="{{ $option->value }}" @selected($routingMode === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="vx32-effects-workspace__routing-plan-field">
                    <span class="vx32-effects-workspace__routing-plan-label">Return Destination</span>
                    <select class="vx32-effects-workspace__routing-plan-input" data-routing-plan-field="return_destination">
                        @foreach (EffectReturnDestination::cases() as $option)
                            <option value="{{ $option->value }}" @selected($returnDestination === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="vx32-effects-workspace__routing-plan-field vx32-effects-workspace__routing-plan-field--level">
                    <span class="vx32-effects-workspace__routing-plan-label">Default Return Level</span>
                    <span class="vx32-effects-workspace__routing-plan-level-wrap">
                        <input
                            type="number"
                            class="vx32-effects-workspace__routing-plan-input vx32-effects-workspace__routing-plan-input--number"
                            data-routing-plan-field="default_return_level"
                            value="{{ $defaultReturnLevelInput }}"
                            step="0.1"
                            inputmode="decimal"
                            placeholder="—"
                        >
                        <span class="vx32-effects-workspace__routing-plan-unit">dB</span>
                    </span>
                </label>
            </div>

            <div class="vx32-effects-workspace__routing-plan-field vx32-effects-workspace__routing-plan-field--target-sections">
                <span class="vx32-effects-workspace__routing-plan-label">Target Sections</span>
                <div class="vx32-effects-workspace__routing-plan-checkboxes">
                    @foreach (EffectRoutingTargetSection::selectableCases() as $option)
                        <label class="vx32-effects-workspace__routing-plan-checkbox">
                            <input
                                type="checkbox"
                                value="{{ $option->value }}"
                                data-routing-plan-target-section
                                @checked(in_array($option->value, $selectedTargetSections, true))
                            >
                            <span>{{ $option->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="vx32-effects-workspace__routing-plan-footer">
                <label class="vx32-effects-workspace__routing-plan-field vx32-effects-workspace__routing-plan-field--notes">
                    <span class="vx32-effects-workspace__routing-plan-label">Notes</span>
                    <textarea
                        class="vx32-effects-workspace__routing-plan-textarea"
                        data-routing-plan-field="notes"
                        rows="2"
                        placeholder="Optional routing notes"
                    >{{ $item->notes }}</textarea>
                </label>

                <dl class="vx32-effects-workspace__routing-plan-summary" data-effect-routing-summary>
                    <div class="vx32-effects-workspace__routing-plan-summary-row">
                        <dt>Mode:</dt>
                        <dd data-summary-routing-mode>{{ $item->routing_mode?->label() ?? $routingMode->label() }}</dd>
                    </div>
                    <div class="vx32-effects-workspace__routing-plan-summary-row">
                        <dt>Target Sections:</dt>
                        <dd data-summary-target-sections>{{ $targetSectionsSummary }}</dd>
                    </div>
                    <div class="vx32-effects-workspace__routing-plan-summary-row">
                        <dt>Return:</dt>
                        <dd data-summary-return-destination>{{ $item->return_destination?->label() ?? $returnDestination->label() }}</dd>
                    </div>
                    <div class="vx32-effects-workspace__routing-plan-summary-row">
                        <dt>Default Return:</dt>
                        <dd data-summary-default-return-level>
                            @if ($defaultReturnLevel !== null)
                                {{ rtrim(rtrim(number_format((float) $defaultReturnLevel, 2, '.', ''), '0'), '.') }} dB
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <p class="vx32-effects-workspace__routing-plan-error" data-effect-routing-error hidden></p>
    </section>

    @if ($parameters->isEmpty())
        <p class="vx32-effects-workspace__effect-card-empty">No parameters available for this effect yet.</p>
    @else
        <div class="vx32-effects-workspace__parameter-cards">
            @foreach ($parameters as $parameter)
                @php
                    $isEnum = $parameter->value_type === 'enum' && ! empty($parameter->enum_values_json);
                    $inputValue = $parameter->value ?? '';
                    $updateUrl = route('shows.console.effects.update-parameter', [$show, $parameter]);
                @endphp
                <label class="vx32-effects-workspace__parameter-card">
                    <span class="vx32-effects-workspace__parameter-card-label">{{ $parameter->parameter_name }}</span>
                    <span class="vx32-effects-workspace__parameter-card-control">
                        @if ($isEnum)
                            <select
                                class="vx32-effects-workspace__parameter-card-input"
                                data-effect-parameter-input
                                data-parameter-id="{{ $parameter->id }}"
                                data-update-url="{{ $updateUrl }}"
                                title="{{ $parameter->parameter_name }}"
                            >
                                @if ($inputValue === '')
                                    <option value="" selected disabled>—</option>
                                @endif
                                @foreach ($parameter->enum_values_json as $option)
                                    <option value="{{ $option }}" @selected($inputValue === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            <input
                                type="text"
                                class="vx32-effects-workspace__parameter-card-input"
                                data-effect-parameter-input
                                data-parameter-id="{{ $parameter->id }}"
                                data-update-url="{{ $updateUrl }}"
                                value="{{ $inputValue }}"
                                placeholder="—"
                                maxlength="6"
                                inputmode="decimal"
                                spellcheck="false"
                                @if ($parameter->min_value !== null) data-min="{{ $parameter->min_value }}" @endif
                                @if ($parameter->max_value !== null) data-max="{{ $parameter->max_value }}" @endif
                                title="{{ $parameter->parameter_name }}"
                            >
                        @endif
                        @if ($parameter->unit)
                            <span class="vx32-effects-workspace__parameter-card-unit">{{ $parameter->unit }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    @endif

    <div class="vx32-effects-workspace__effect-card-actions">
        <div
            class="vx32-effects-workspace__effect-deploy"
            data-effect-deploy
            data-deploy-url="{{ $deployUrl }}"
            data-has-slot="{{ $currentSlot !== null ? 'true' : 'false' }}"
            data-live-control="{{ $deployLiveAvailable ? 'true' : 'false' }}"
            data-initial-status="{{ $deployInitialStatus }}"
        >
            <button
                type="button"
                class="vx32-effects-workspace__effect-deploy-btn"
                data-effect-deploy-button
                @disabled(! $deployButtonEnabled)
                @if (! $deployButtonEnabled && $currentSlot !== null && ! $deployLiveAvailable)
                    title="{{ $effectDeployControl['reason'] ?? 'Live X32 control is required to deploy effects.' }}"
                @elseif (! $deployButtonEnabled && $currentSlot === null)
                    title="Allocate an FX slot before deploying."
                @endif
            >Deploy Effect</button>
            <span
                class="vx32-effects-workspace__effect-deploy-status vx32-effects-workspace__effect-deploy-status--{{ $deployInitialStatus }}"
                data-effect-deploy-status
            >{{ $deployInitialLabel }}</span>
            <p class="vx32-effects-workspace__effect-deploy-error" data-effect-deploy-error hidden></p>
        </div>
        <a
            href="{{ route('shows.console.effects.edit-package-item', [$show, $item]) }}"
            class="vx32-routing-detail__configure"
        >Edit</a>
        <form
            method="POST"
            action="{{ route('shows.console.effects.destroy-package-item', [$show, $item]) }}"
            class="vx32-effects-workspace__effect-delete-form"
            onsubmit="return confirm('Remove {{ $displayName }} from this package? Copied parameters and routing plan rows will be deleted.');"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="vx32-effects-workspace__effect-delete-btn">Delete</button>
        </form>
    </div>
</div>
