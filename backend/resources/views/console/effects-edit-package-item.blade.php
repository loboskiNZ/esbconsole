@php
    $x32Effect = $item->x32Effect;
    $definition = $item->effectDefinition;
    $displayName = $x32Effect?->displayName() ?? $definition?->name ?? 'Unknown Effect';
    $slotGroup = $x32Effect?->x32_slot_group ?? $definition?->x32_slot_group;
    $allowedSlots = $slotGroup?->allowedSlotNumbers() ?? range(1, 8);
    $allowedHelper = $slotGroup?->allowedSlotsHelper() ?? 'FX1–FX8';
    $currentSlot = old('preferred_slot_number', $item->preferred_slot_number);
    $selectedTargetSections = old('target_sections', $item->resolvedTargetSectionValues());
    $defaultReturnLevel = old('default_return_level', $item->default_return_level);
    $defaultReturnLevelInput = $defaultReturnLevel !== null && $defaultReturnLevel !== ''
        ? rtrim(rtrim(number_format((float) $defaultReturnLevel, 2, '.', ''), '0'), '.')
        : '';
    $parameters = $item->parameters->sortBy('parameter_number')->values();
@endphp

<x-console-layout>
    <div class="vx32-console">
        @include('console._console-header', [
            'show' => $show,
            'consoleType' => $consoleType,
            'workspaceMode' => $workspaceMode,
            'summary' => $summary,
            'activeTab' => 'effects',
        ])

        <div class="vx32-routing-workspace vx32-effects-workspace vx32-effects-form-workspace">
            <header class="vx32-routing-workspace__header">
                <div class="vx32-routing-workspace__header-left">
                    <span class="vx32-routing-workspace__context">ESB Console</span>
                    <div class="vx32-routing-workspace__title-row">
                        <h1 class="vx32-routing-workspace__title">Edit Package Effect</h1>
                    </div>
                    <p class="vx32-effects-form__hint">{{ $package->name }} · {{ $displayName }}</p>
                </div>
                <div class="vx32-routing-workspace__header-actions">
                    <a
                        href="{{ route('shows.console.effects', ['show' => $show, 'package' => $package->id]) }}"
                        class="vx32-effects-form__cancel-link"
                    >Back to Effects</a>
                </div>
            </header>

            <div class="vx32-effects-form-workspace__body">
                <form
                    method="POST"
                    action="{{ route('shows.console.effects.update-package-item', [$show, $item]) }}"
                    class="vx32-effects-form"
                >
                    @csrf
                    @method('PATCH')

                    <fieldset class="vx32-effects-form__fieldset">
                        <legend class="vx32-effects-form__legend">Slot allocation</legend>
                        <div class="vx32-effects-form__field">
                            <label for="preferred-slot" class="vx32-effects-form__label">Preferred slot</label>
                            <select id="preferred-slot" name="preferred_slot_number" class="vx32-effects-form__select">
                                <option value="" @selected($currentSlot === null || $currentSlot === '')>Not allocated</option>
                                @foreach ($allowedSlots as $slot)
                                    @php
                                        $slotConflict = $slotAvailability->reasonForSlot($item, $slot);
                                        $isUnavailable = $slotConflict !== null;
                                        $isPermanentReservation = ($slotConflict['reason'] ?? null) === 'permanent_reservation';
                                    @endphp
                                    <option
                                        value="{{ $slot }}"
                                        @selected((string) $currentSlot === (string) $slot)
                                        @disabled($isUnavailable)
                                        @if ($isPermanentReservation) data-permanent-reserved="1" @endif
                                    >
                                        FX{{ $slot }}@if ($isUnavailable) ({{ $isPermanentReservation ? 'reserved' : 'in use' }})@endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="vx32-effects-form__hint">Allowed: {{ $allowedHelper }}</p>
                            @error('preferred_slot_number')
                                <p class="vx32-effects-form__error">{{ $message }}</p>
                            @enderror
                        </div>
                    </fieldset>

                    <fieldset class="vx32-effects-form__fieldset">
                        <legend class="vx32-effects-form__legend">Routing plan</legend>

                        <div class="vx32-effects-form__field">
                            <label for="routing-mode" class="vx32-effects-form__label">Routing mode</label>
                            <select id="routing-mode" name="routing_mode" class="vx32-effects-form__select">
                                @foreach ($routingModes as $option)
                                    <option
                                        value="{{ $option->value }}"
                                        @selected(old('routing_mode', $item->routing_mode?->value) === $option->value)
                                    >{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @error('routing_mode')
                                <p class="vx32-effects-form__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="vx32-effects-form__field">
                            <span class="vx32-effects-form__label">Target sections</span>
                            <div class="vx32-effects-workspace__routing-plan-checkboxes">
                                @foreach ($targetSectionOptions as $option)
                                    <label class="vx32-effects-workspace__routing-plan-checkbox">
                                        <input
                                            type="checkbox"
                                            name="target_sections[]"
                                            value="{{ $option->value }}"
                                            @checked(in_array($option->value, $selectedTargetSections, true))
                                        >
                                        <span>{{ $option->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('target_sections')
                                <p class="vx32-effects-form__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="vx32-effects-form__field">
                            <label for="return-destination" class="vx32-effects-form__label">Return destination</label>
                            <select id="return-destination" name="return_destination" class="vx32-effects-form__select">
                                @foreach ($returnDestinations as $option)
                                    <option
                                        value="{{ $option->value }}"
                                        @selected(old('return_destination', $item->return_destination?->value) === $option->value)
                                    >{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @error('return_destination')
                                <p class="vx32-effects-form__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="vx32-effects-form__field">
                            <label for="default-return-level" class="vx32-effects-form__label">Default return level (dB)</label>
                            <input
                                id="default-return-level"
                                name="default_return_level"
                                type="number"
                                step="0.1"
                                inputmode="decimal"
                                value="{{ $defaultReturnLevelInput }}"
                                class="vx32-effects-form__input"
                            >
                            @error('default_return_level')
                                <p class="vx32-effects-form__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="vx32-effects-form__field">
                            <label for="routing-notes" class="vx32-effects-form__label">Notes</label>
                            <textarea
                                id="routing-notes"
                                name="notes"
                                rows="3"
                                class="vx32-effects-form__textarea"
                            >{{ old('notes', $item->notes) }}</textarea>
                            @error('notes')
                                <p class="vx32-effects-form__error">{{ $message }}</p>
                            @enderror
                        </div>
                    </fieldset>

                    @if ($parameters->isNotEmpty())
                        <fieldset class="vx32-effects-form__fieldset">
                            <legend class="vx32-effects-form__legend">Copied parameters</legend>
                            <div class="vx32-effects-workspace__parameter-cards">
                                @foreach ($parameters as $parameter)
                                    @php
                                        $isEnum = $parameter->value_type === 'enum' && ! empty($parameter->enum_values_json);
                                        $inputValue = old('parameters.'.$parameter->id, $parameter->value ?? '');
                                    @endphp
                                    <label class="vx32-effects-workspace__parameter-card">
                                        <span class="vx32-effects-workspace__parameter-card-label">{{ $parameter->parameter_name }}</span>
                                        <span class="vx32-effects-workspace__parameter-card-control">
                                            @if ($isEnum)
                                                <select
                                                    name="parameters[{{ $parameter->id }}]"
                                                    class="vx32-effects-workspace__parameter-card-input"
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
                                                    name="parameters[{{ $parameter->id }}]"
                                                    class="vx32-effects-workspace__parameter-card-input"
                                                    value="{{ $inputValue }}"
                                                    maxlength="6"
                                                    inputmode="decimal"
                                                    spellcheck="false"
                                                >
                                            @endif
                                            @if ($parameter->unit)
                                                <span class="vx32-effects-workspace__parameter-card-unit">{{ $parameter->unit }}</span>
                                            @endif
                                        </span>
                                    </label>
                                    @error('parameters.'.$parameter->id)
                                        <p class="vx32-effects-form__error">{{ $message }}</p>
                                    @enderror
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <div class="vx32-effects-form__actions">
                        <button type="submit" class="vx32-effects-form__submit-btn">Save Effect</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-console-layout>
