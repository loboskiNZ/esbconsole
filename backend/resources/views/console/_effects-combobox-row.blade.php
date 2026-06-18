<div class="vx32-effects-form__selector-row" data-effects-selector-row>
    <div
        class="vx32-effects-combobox"
        data-effects-combobox
        data-highlight-index="-1"
    >
        <input
            type="hidden"
            name="effect_ids[]"
            value="{{ $selectedId }}"
            class="vx32-effects-combobox__value"
            data-effects-combobox-value
            @if ($required) required @endif
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
    @if ($showRemove)
        <button type="button" class="vx32-effects-form__remove-btn" data-remove-effect-row>Remove</button>
    @endif
</div>
