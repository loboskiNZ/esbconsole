@php
    $effectOptions = $x32Effects->map(function ($x32Effect) {
        return [
            'id' => $x32Effect->id,
            'label' => $x32Effect->selectorPrimaryLabel(),
            'help' => $x32Effect->selectorSecondaryLabel(),
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

    $effectLabelsById = $effectOptions->pluck('label', 'id');
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
                        <h1 class="vx32-routing-workspace__title">New Effect Package</h1>
                    </div>
                </div>
                <div class="vx32-routing-workspace__header-actions">
                    <a href="{{ route('shows.console.effects', $show) }}" class="vx32-effects-form__cancel-link">Back to Effects</a>
                </div>
            </header>

            <div class="vx32-effects-form-workspace__body">
                <form
                    method="POST"
                    action="{{ route('shows.console.effects.store-package', $show) }}"
                    class="vx32-effects-form"
                    id="effects-new-package-form"
                >
                    @csrf

                    <div class="vx32-effects-form__field">
                        <label for="package-name" class="vx32-effects-form__label">Package name</label>
                        <input
                            id="package-name"
                            name="name"
                            type="text"
                            maxlength="100"
                            value="{{ old('name') }}"
                            class="vx32-effects-form__input"
                            required
                        >
                        @error('name')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="vx32-effects-form__field">
                        <label for="package-description" class="vx32-effects-form__label">Package description</label>
                        <textarea
                            id="package-description"
                            name="description"
                            rows="3"
                            class="vx32-effects-form__textarea"
                        >{{ old('description') }}</textarea>
                        @error('description')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="vx32-effects-form__field">
                        <label for="package-type" class="vx32-effects-form__label">Effect type</label>
                        <select id="package-type" name="effect_package_type_id" class="vx32-effects-form__select" required>
                            <option value="">Select package type</option>
                            @foreach ($packageTypes as $packageType)
                                <option
                                    value="{{ $packageType->id }}"
                                    @selected((string) old('effect_package_type_id') === (string) $packageType->id)
                                >{{ $packageType->name }}</option>
                            @endforeach
                        </select>
                        @error('effect_package_type_id')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <fieldset class="vx32-effects-form__fieldset">
                        <legend class="vx32-effects-form__legend">Choose effects</legend>
                        <p class="vx32-effects-form__hint">Type to filter the X32 algorithm list, then pick an effect.</p>

                        <div id="effects-selector-list" class="vx32-effects-form__selector-list">
                            @php
                                $oldEffectIds = old('effect_ids', ['']);
                            @endphp
                            @foreach ($oldEffectIds as $index => $selectedId)
                                @include('console._effects-combobox-row', [
                                    'selectedId' => $selectedId,
                                    'selectedLabel' => $effectLabelsById[(int) $selectedId] ?? '',
                                    'required' => $index === 0,
                                    'showRemove' => $index > 0,
                                ])
                            @endforeach
                        </div>

                        <button type="button" class="vx32-effects-form__add-btn" id="add-effect-selector">Add another effect</button>

                        @error('effect_ids')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                        @error('effect_ids.*')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="vx32-effects-form__actions">
                        <button type="submit" class="vx32-effects-form__submit-btn">Save Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="application/json" id="x32-effects-options">@json($effectOptions)</script>

    <template id="effects-selector-row-template">
        @include('console._effects-combobox-row', [
            'selectedId' => '',
            'selectedLabel' => '',
            'required' => false,
            'showRemove' => true,
        ])
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const options = JSON.parse(document.getElementById('x32-effects-options')?.textContent || '[]');
            const list = document.getElementById('effects-selector-list');
            const template = document.getElementById('effects-selector-row-template');
            const addButton = document.getElementById('add-effect-selector');
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

            const renderMatches = (combobox, matches, highlightIndex = 0) => {
                const listEl = combobox.querySelector('[data-effects-combobox-list]');
                const dropdown = combobox.querySelector('[data-effects-combobox-dropdown]');

                if (!listEl || !dropdown) {
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

            const closeDropdown = (combobox) => {
                const dropdown = combobox.querySelector('[data-effects-combobox-dropdown]');
                if (dropdown) {
                    dropdown.hidden = true;
                }
                combobox.dataset.highlightIndex = '-1';
            };

            const selectOption = (combobox, id, label) => {
                const valueInput = combobox.querySelector('[data-effects-combobox-value]');
                const textInput = combobox.querySelector('[data-effects-combobox-input]');

                if (valueInput) {
                    valueInput.value = id;
                }

                if (textInput) {
                    textInput.value = label;
                }

                closeDropdown(combobox);
            };

            const clearSelection = (combobox) => {
                const valueInput = combobox.querySelector('[data-effects-combobox-value]');
                if (valueInput) {
                    valueInput.value = '';
                }
            };

            const initCombobox = (combobox) => {
                if (!(combobox instanceof HTMLElement) || combobox.dataset.initialized === 'true') {
                    return;
                }

                combobox.dataset.initialized = 'true';
                const textInput = combobox.querySelector('[data-effects-combobox-input]');
                const dropdown = combobox.querySelector('[data-effects-combobox-dropdown]');
                const listEl = combobox.querySelector('[data-effects-combobox-list]');

                if (!(textInput instanceof HTMLInputElement) || !dropdown || !listEl) {
                    return;
                }

                textInput.addEventListener('focus', () => {
                    renderMatches(combobox, filterOptions(textInput.value));
                });

                textInput.addEventListener('input', () => {
                    clearSelection(combobox);
                    renderMatches(combobox, filterOptions(textInput.value));
                });

                textInput.addEventListener('keydown', (event) => {
                    const matches = filterOptions(textInput.value);
                    let highlightIndex = Number.parseInt(combobox.dataset.highlightIndex || '0', 10);

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        highlightIndex = Math.min(highlightIndex + 1, Math.max(matches.length - 1, 0));
                        renderMatches(combobox, matches, highlightIndex);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        highlightIndex = Math.max(highlightIndex - 1, 0);
                        renderMatches(combobox, matches, highlightIndex);
                    } else if (event.key === 'Enter') {
                        const highlighted = matches[highlightIndex];
                        if (highlighted) {
                            event.preventDefault();
                            selectOption(combobox, String(highlighted.id), highlighted.label);
                        }
                    } else if (event.key === 'Escape') {
                        closeDropdown(combobox);
                    }
                });

                listEl.addEventListener('mousedown', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }

                    const option = target.closest('[data-effect-id]');
                    if (!(option instanceof HTMLElement)) {
                        return;
                    }

                    event.preventDefault();
                    selectOption(combobox, option.dataset.effectId || '', option.dataset.effectLabel || '');
                });

                document.addEventListener('click', (event) => {
                    if (!(event.target instanceof Node) || combobox.contains(event.target)) {
                        return;
                    }

                    closeDropdown(combobox);
                });
            };

            const initAllComboboxes = (root) => {
                root.querySelectorAll('[data-effects-combobox]').forEach((combobox) => initCombobox(combobox));
            };

            initAllComboboxes(document);

            addButton?.addEventListener('click', () => {
                if (!template?.content?.firstElementChild) {
                    return;
                }

                const row = template.content.firstElementChild.cloneNode(true);
                list?.appendChild(row);
                initAllComboboxes(row);
            });

            list?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                if (!target.matches('[data-remove-effect-row]')) {
                    return;
                }

                const row = target.closest('[data-effects-selector-row]');
                row?.remove();
            });
        });
    </script>
</x-console-layout>
