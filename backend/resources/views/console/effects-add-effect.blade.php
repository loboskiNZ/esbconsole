@php
    $selectedEffectId = (int) old('effect_id', 0);
    $selectedEffect = $x32Effects->firstWhere('id', $selectedEffectId);
    $selectedLabel = $selectedEffect?->selectorPrimaryLabel() ?? '';

    $effectOptions = $x32Effects->map(function ($x32Effect) {
        return [
            'id' => $x32Effect->id,
            'label' => $x32Effect->selectorPrimaryLabel(),
            'help' => $x32Effect->selectorSecondaryLabel(),
            'allowedSlots' => $x32Effect->x32_slot_group->allowedSlotNumbers(),
            'allowedHelper' => $x32Effect->x32_slot_group->allowedSlotsHelper(),
            'search' => strtolower(implode(' ', array_filter([
                $x32Effect->operator_name,
                $x32Effect->effect_name,
                $x32Effect->effect_code,
                $x32Effect->operator_category,
                $x32Effect->operator_description,
                (string) $x32Effect->x32_algorithm_id,
                $x32Effect->slotGroupLabel(),
                $x32Effect->x32_slot_group->value,
                implode(' ', $x32Effect->recommendedForTargets()),
            ]))),
        ];
    })->values();

    $oldSlot = old('preferred_slot_number');
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
                        <h1 class="vx32-routing-workspace__title">Add Effect</h1>
                    </div>
                    <p class="vx32-effects-form__hint">{{ $package->name }}</p>
                </div>
                <div class="vx32-routing-workspace__header-actions">
                    <a
                        href="{{ route('shows.console.effects', ['show' => $show, 'package' => $package->id]) }}"
                        class="vx32-effects-form__cancel-link"
                    >Cancel</a>
                </div>
            </header>

            <div class="vx32-effects-form-workspace__body">
                <form
                    method="POST"
                    action="{{ route('shows.console.effects.store-effect', [$show, $package]) }}"
                    class="vx32-effects-form"
                    id="effects-add-effect-form"
                >
                    @csrf

                    <fieldset class="vx32-effects-form__fieldset">
                        <legend class="vx32-effects-form__legend">Choose effect</legend>
                        <p class="vx32-effects-form__hint">Type to filter the X32 algorithm catalogue, then pick an effect.</p>

                        <div
                            class="vx32-effects-combobox"
                            data-effects-combobox
                            data-highlight-index="-1"
                        >
                            <input
                                type="hidden"
                                name="effect_id"
                                value="{{ $selectedEffectId ?: '' }}"
                                class="vx32-effects-combobox__value"
                                data-effects-combobox-value
                                required
                            >
                            <input
                                type="text"
                                class="vx32-effects-combobox__input vx32-effects-form__input"
                                data-effects-combobox-input
                                value="{{ $selectedLabel }}"
                                placeholder="Type to search X32 effects..."
                                autocomplete="off"
                                spellcheck="false"
                                aria-autocomplete="list"
                                aria-expanded="false"
                            >
                            <div class="vx32-effects-combobox__dropdown" data-effects-combobox-dropdown hidden>
                                <ul class="vx32-effects-combobox__list" data-effects-combobox-list role="listbox"></ul>
                            </div>
                        </div>

                        @error('effect_id')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="vx32-effects-form__field">
                        <label for="preferred-slot" class="vx32-effects-form__label">Optional slot allocation</label>
                        <select
                            id="preferred-slot"
                            name="preferred_slot_number"
                            class="vx32-effects-form__select"
                            data-add-effect-slot-select
                        >
                            <option value="" @selected($oldSlot === null || $oldSlot === '')>Not allocated</option>
                            @foreach (range(1, 8) as $slot)
                                @php
                                    $unavailable = $unavailableSlots->get($slot);
                                @endphp
                                <option
                                    value="{{ $slot }}"
                                    data-slot-option
                                    @selected((string) $oldSlot === (string) $slot)
                                    @disabled($unavailable !== null)
                                    @if ($unavailable) data-conflict-reason="{{ $unavailable['reason'] }}" @endif
                                >
                                    FX{{ $slot }}@if ($unavailable) ({{ $unavailable['reason'] === 'permanent_reservation' ? 'reserved' : 'in use' }})@endif
                                </option>
                            @endforeach
                        </select>
                        <p class="vx32-effects-form__hint" data-add-effect-slot-helper>Select an effect to see allowed slots.</p>
                        @error('preferred_slot_number')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="vx32-effects-form__actions">
                        <button type="submit" class="vx32-effects-form__submit-btn">Save Effect</button>
                        <a
                            href="{{ route('shows.console.effects', ['show' => $show, 'package' => $package->id]) }}"
                            class="vx32-effects-form__cancel-link"
                        >Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="application/json" id="x32-effects-options">@json($effectOptions)</script>
    <script type="application/json" id="x32-add-effect-unavailable-slots">@json($unavailableSlots)</script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const options = JSON.parse(document.getElementById('x32-effects-options')?.textContent || '[]');
            const unavailableSlots = JSON.parse(document.getElementById('x32-add-effect-unavailable-slots')?.textContent || '{}');
            const combobox = document.querySelector('[data-effects-combobox]');
            const slotSelect = document.querySelector('[data-add-effect-slot-select]');
            const slotHelper = document.querySelector('[data-add-effect-slot-helper]');
            const maxVisibleMatches = 12;

            const normalize = (value) => value.trim().toLowerCase();

            const filterOptions = (query) => {
                const terms = normalize(query).split(/\s+/).filter(Boolean);

                if (terms.length === 0) {
                    return options.slice(0, maxVisibleMatches);
                }

                return options
                    .filter((option) => terms.every((term) => option.search.includes(term)))
                    .slice(0, maxVisibleMatches);
            };

            const closeDropdown = () => {
                const dropdown = combobox?.querySelector('[data-effects-combobox-dropdown]');
                if (dropdown) {
                    dropdown.hidden = true;
                }
                if (combobox) {
                    combobox.dataset.highlightIndex = '-1';
                }
            };

            const renderMatches = (matches, highlightIndex = 0) => {
                const listEl = combobox?.querySelector('[data-effects-combobox-list]');
                const dropdown = combobox?.querySelector('[data-effects-combobox-dropdown]');

                if (!listEl || !dropdown || !combobox) {
                    return;
                }

                listEl.innerHTML = '';

                if (matches.length === 0) {
                    const empty = document.createElement('li');
                    empty.className = 'vx32-effects-combobox__empty';
                    empty.textContent = 'No matching X32 effects.';
                    listEl.appendChild(empty);
                    dropdown.hidden = false;
                    combobox.dataset.highlightIndex = '-1';
                    return;
                }

                matches.forEach((option, index) => {
                    const item = document.createElement('li');
                    item.className = 'vx32-effects-combobox__option';
                    if (index === highlightIndex) {
                        item.classList.add('is-highlighted');
                    }
                    item.dataset.effectId = String(option.id);
                    item.dataset.effectLabel = option.label;

                    const primary = document.createElement('span');
                    primary.className = 'vx32-effects-combobox__option-primary';
                    primary.textContent = option.label;
                    item.appendChild(primary);

                    if (option.help) {
                        const help = document.createElement('span');
                        help.className = 'vx32-effects-combobox__option-help';
                        help.textContent = option.help;
                        item.appendChild(help);
                    }

                    listEl.appendChild(item);
                });

                dropdown.hidden = false;
                combobox.dataset.highlightIndex = String(highlightIndex);
            };

            const selectOption = (id, label) => {
                const valueInput = combobox?.querySelector('[data-effects-combobox-value]');
                const textInput = combobox?.querySelector('[data-effects-combobox-input]');

                if (valueInput) {
                    valueInput.value = id;
                }

                if (textInput) {
                    textInput.value = label;
                }

                closeDropdown();
                updateSlotOptions(Number.parseInt(id, 10));
            };

            const updateSlotOptions = (effectId) => {
                if (!slotSelect) {
                    return;
                }

                const effect = options.find((option) => option.id === effectId);
                const allowed = effect?.allowedSlots ?? [];
                const helper = effect?.allowedHelper ?? 'FX1–FX8';

                if (slotHelper) {
                    slotHelper.textContent = effect ? `Allowed: ${helper}` : 'Select an effect to see allowed slots.';
                }

                slotSelect.querySelectorAll('[data-slot-option]').forEach((option) => {
                    const slot = Number.parseInt(option.value, 10);
                    const unavailable = unavailableSlots[String(slot)] ?? unavailableSlots[slot];
                    const allowedForEffect = allowed.includes(slot);
                    const disabled = !allowedForEffect || unavailable !== undefined;

                    option.disabled = disabled;
                    option.hidden = !allowedForEffect;

                    if (!allowedForEffect) {
                        option.textContent = `FX${slot}`;
                        return;
                    }

                    option.textContent = disabled
                        ? `FX${slot} (${unavailable?.reason === 'permanent_reservation' ? 'reserved' : 'in use'})`
                        : `FX${slot}`;
                });

                if (slotSelect.value !== '' && slotSelect.selectedOptions[0]?.disabled) {
                    slotSelect.value = '';
                }
            };

            if (combobox) {
                const textInput = combobox.querySelector('[data-effects-combobox-input]');
                const listEl = combobox.querySelector('[data-effects-combobox-list]');

                textInput?.addEventListener('focus', () => {
                    renderMatches(filterOptions(textInput.value));
                });

                textInput?.addEventListener('input', () => {
                    const valueInput = combobox.querySelector('[data-effects-combobox-value]');
                    if (valueInput) {
                        valueInput.value = '';
                    }
                    renderMatches(filterOptions(textInput.value));
                    updateSlotOptions(0);
                });

                textInput?.addEventListener('keydown', (event) => {
                    const matches = filterOptions(textInput.value);
                    let highlightIndex = Number.parseInt(combobox.dataset.highlightIndex || '0', 10);

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        highlightIndex = Math.min(highlightIndex + 1, Math.max(matches.length - 1, 0));
                        renderMatches(matches, highlightIndex);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        highlightIndex = Math.max(highlightIndex - 1, 0);
                        renderMatches(matches, highlightIndex);
                    } else if (event.key === 'Enter') {
                        const highlighted = matches[highlightIndex];
                        if (highlighted) {
                            event.preventDefault();
                            selectOption(String(highlighted.id), highlighted.label);
                        }
                    } else if (event.key === 'Escape') {
                        closeDropdown();
                    }
                });

                listEl?.addEventListener('mousedown', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }

                    const option = target.closest('[data-effect-id]');
                    if (!(option instanceof HTMLElement)) {
                        return;
                    }

                    event.preventDefault();
                    selectOption(option.dataset.effectId || '', option.dataset.effectLabel || '');
                });

                document.addEventListener('click', (event) => {
                    if (!(event.target instanceof Node) || combobox.contains(event.target)) {
                        return;
                    }

                    closeDropdown();
                });
            }

            const initialEffectId = Number.parseInt(combobox?.querySelector('[data-effects-combobox-value]')?.value || '0', 10);
            if (initialEffectId > 0) {
                updateSlotOptions(initialEffectId);
            }
        });
    </script>
</x-console-layout>
