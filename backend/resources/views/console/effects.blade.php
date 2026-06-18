@php
    $countFxSlots = static function ($package): int {
        return $package->effectPackageItems
            ->filter(function ($item) {
                if ($item->x32Effect) {
                    return $item->x32Effect->countsTowardFxSlotLimit();
                }

                if ($item->effectLibraryItem) {
                    return $item->effectLibraryItem->countsTowardFxSlotLimit();
                }

                return $item->effectDefinition?->implementation_type?->value === 'fx_slot'
                    || $item->effectDefinition?->implementation_type?->value === 'hybrid';
            })
            ->count();
    };
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

        <div class="vx32-routing-workspace vx32-effects-workspace">
            <header class="vx32-routing-workspace__header">
                <div class="vx32-routing-workspace__header-left">
                    <span class="vx32-routing-workspace__context">ESB Console</span>
                    <div class="vx32-routing-workspace__title-row">
                        <h1 class="vx32-routing-workspace__title">Effects</h1>
                    </div>
                </div>
            </header>

            <div class="vx32-effects-workspace__grid">
                <section
                    class="vx32-effects-workspace__col vx32-effects-workspace__col--packages"
                    aria-labelledby="effects-packages-title"
                >
                    <article class="vx32-effects-workspace__card">
                        <header class="vx32-effects-workspace__card-header vx32-effects-workspace__card-header--actions">
                            <h2 id="effects-packages-title" class="vx32-effects-workspace__card-title">Effect Packages</h2>
                            <a
                                href="{{ route('shows.console.effects.new-package', $show) }}"
                                class="vx32-effects-workspace__new-btn"
                            >New</a>
                        </header>
                        <div class="vx32-effects-workspace__card-body">
                            @if ($packages->isEmpty())
                                <p class="vx32-effects-workspace__placeholder-text">No effect packages yet. Use New to create one.</p>
                            @else
                                <ul class="vx32-effects-workspace__package-list">
                                    @foreach ($packages as $package)
                                        @php
                                            $isSelected = $selectedPackage && $selectedPackage->id === $package->id;
                                            $slotCount = $countFxSlots($package);
                                        @endphp
                                        <li class="vx32-effects-workspace__package-row {{ $isSelected ? 'is-selected' : '' }}">
                                            <div class="vx32-effects-workspace__package-row-inner">
                                                <a
                                                    href="{{ route('shows.console.effects', ['show' => $show, 'package' => $package->id]) }}"
                                                    class="vx32-effects-workspace__package-link"
                                                >
                                                    <div class="vx32-effects-workspace__package-row-main">
                                                        <span class="vx32-effects-workspace__package-name">{{ $package->name }}</span>
                                                        <span class="vx32-effects-workspace__badge">
                                                            {{ $package->effectPackageTypeOption?->name ?? $package->package_type->name }}
                                                        </span>
                                                    </div>
                                                    <div class="vx32-effects-workspace__package-meta">
                                                        <span>{{ $slotCount }} FX slot{{ $slotCount === 1 ? '' : 's' }}</span>
                                                        <span>{{ $package->effectPackageItems->count() }} effect{{ $package->effectPackageItems->count() === 1 ? '' : 's' }}</span>
                                                    </div>
                                                </a>
                                                <div class="vx32-effects-workspace__package-actions">
                                                    <a
                                                        href="{{ route('shows.console.effects.edit-package', [$show, $package]) }}"
                                                        class="vx32-effects-workspace__package-action"
                                                    >Edit</a>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('shows.console.effects.destroy-package', [$show, $package]) }}"
                                                        class="vx32-effects-workspace__package-delete-form"
                                                        onsubmit="return confirm('Delete package {{ $package->name }}? This removes all copied planning data for this package.');"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="vx32-effects-workspace__package-action vx32-effects-workspace__package-action--danger">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </article>
                </section>

                <section
                    class="vx32-effects-workspace__col vx32-effects-workspace__col--details"
                    aria-labelledby="effects-details-title"
                >
                    <article class="vx32-effects-workspace__card">
                        <header class="vx32-effects-workspace__card-header vx32-effects-workspace__card-header--actions">
                            <div class="vx32-effects-workspace__detail-header-main">
                                <h2 id="effects-details-title" class="vx32-effects-workspace__card-title">Effect Details</h2>
                                @if ($selectedPackage)
                                    <p class="vx32-effects-workspace__card-subtitle">{{ $selectedPackage->name }}</p>
                                @endif
                            </div>
                            @if ($selectedPackage)
                                <a
                                    href="{{ route('shows.console.effects.add-effect', [$show, $selectedPackage]) }}"
                                    class="vx32-effects-workspace__new-btn"
                                >Add Effect</a>
                            @endif
                        </header>
                        <div class="vx32-effects-workspace__card-body">
                            @if (! $selectedPackage)
                                <p class="vx32-effects-workspace__placeholder-text">Select a package to view details.</p>
                            @else
                                @if ($selectedPackage->description)
                                    <p class="vx32-effects-workspace__package-overview">{{ $selectedPackage->description }}</p>
                                @endif

                                @if ($selectedPackage->effectPackageItems->isEmpty())
                                    <p class="vx32-effects-workspace__placeholder-text">No effects have been added to this package yet.</p>
                                @else
                                    <div class="vx32-routing-detail__input-cards vx32-effects-workspace__effect-detail-cards">
                                        @foreach ($selectedPackage->effectPackageItems as $item)
                                            @include('console._effects-package-effect-card', [
                                                'item' => $item,
                                                'show' => $show,
                                                'package' => $selectedPackage,
                                                'routingPlanSuggester' => $routingPlanSuggester,
                                                'slotAvailability' => $slotAvailability,
                                                'effectDeployControl' => $effectDeployControl,
                                            ])
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                        </div>
                    </article>
                </section>

                <section
                    class="vx32-effects-workspace__col vx32-effects-workspace__col--summary"
                    aria-labelledby="effects-summary-title"
                >
                    <article class="vx32-effects-workspace__card">
                        <header class="vx32-effects-workspace__card-header">
                            <h2 id="effects-summary-title" class="vx32-effects-workspace__card-title">Selected Package Deployment Plan</h2>
                        </header>
                        <div class="vx32-effects-workspace__card-body">
                            @include('console._effects-deployment-plan-preview', [
                                'deploymentPlan' => $deploymentPlan,
                            ])
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </div>
</x-console-layout>
