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
                        <h1 class="vx32-routing-workspace__title">Edit Effect Package</h1>
                    </div>
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
                    action="{{ route('shows.console.effects.update-package', [$show, $package]) }}"
                    class="vx32-effects-form"
                >
                    @csrf
                    @method('PATCH')

                    <div class="vx32-effects-form__field">
                        <label for="package-name" class="vx32-effects-form__label">Package name</label>
                        <input
                            id="package-name"
                            name="name"
                            type="text"
                            maxlength="100"
                            value="{{ old('name', $package->name) }}"
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
                        >{{ old('description', $package->description) }}</textarea>
                        @error('description')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="vx32-effects-form__field">
                        <label for="package-type" class="vx32-effects-form__label">Package type</label>
                        <select id="package-type" name="effect_package_type_id" class="vx32-effects-form__select" required>
                            @foreach ($packageTypes as $packageType)
                                <option
                                    value="{{ $packageType->id }}"
                                    @selected((string) old('effect_package_type_id', $package->effect_package_type_id) === (string) $packageType->id)
                                >{{ $packageType->name }}</option>
                            @endforeach
                        </select>
                        @error('effect_package_type_id')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="vx32-effects-form__field">
                        <label class="vx32-effects-form__checkbox">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                @checked(old('is_active', $package->is_active ? '1' : '0') == '1')
                            >
                            <span>Package is active</span>
                        </label>
                        @error('is_active')
                            <p class="vx32-effects-form__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="vx32-effects-form__actions">
                        <button type="submit" class="vx32-effects-form__submit-btn">Save Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-console-layout>
