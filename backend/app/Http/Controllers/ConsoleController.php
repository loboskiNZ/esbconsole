<?php

namespace App\Http\Controllers;

use App\Enums\ConsoleLearningStatus;
use App\Enums\ConsoleType;
use App\Enums\EffectReturnDestination;
use App\Enums\EffectRoutingMode;
use App\Enums\EffectRoutingTargetSection;
use App\Http\Controllers\Concerns\ResolvesBand;
use App\Http\Requests\Console\StoreAddEffectToPackageRequest;
use App\Http\Requests\Console\StoreConsoleEffectPackageRequest;
use App\Http\Requests\Console\UpdateConsoleEffectPackageItemRequest;
use App\Http\Requests\Console\UpdateConsoleEffectPackageRequest;
use App\Models\ConsoleLearningSnapshot;
use App\Models\X32Effect;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use App\Models\EffectPackageItemParameter;
use App\Models\EffectPackageTypeOption;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\Console\ShowConsoleBaselineService;
use App\Services\Console\ShowConsoleControlService;
use App\Services\Console\ShowConsoleMonitorBusEqControlService;
use App\Services\Console\ShowConsoleMonitorBusMasterControlService;
use App\Services\Console\ShowConsoleMonitorSendControlService;
use App\Services\Console\ShowConsoleParameterService;
use App\Services\Console\ShowConsoleStripEnricher;
use App\Services\Console\ShowConsoleWorkspaceResolver;
use App\Services\Console\VirtualConsoleStripBuilder;
use App\Services\Console\X32ConsoleLearningService;
use App\Services\Console\X32ConfigurationWorkspaceBuilder;
use App\Services\Console\X32MonitorsWorkspaceBuilder;
use App\Services\Console\X32RoutingWorkspaceBuilder;
use App\Services\Effects\AddEffectToPackageService;
use App\Services\Effects\CreateEffectPackageService;
use App\Services\Effects\DeleteEffectPackageItemService;
use App\Services\Effects\DeleteEffectPackageService;
use App\Services\Effects\DeployEffectPackageItemService;
use App\Services\Effects\EffectPackageDeploymentPlanPreviewService;
use App\Services\Effects\EffectPackageItemSlotAvailabilityService;
use App\Services\Effects\EffectRoutingPlanSuggester;
use App\Services\Effects\UpdateEffectPackageItemParameterService;
use App\Services\Effects\UpdateEffectPackageItemRoutingPlanService;
use App\Services\Effects\UpdateEffectPackageItemService;
use App\Services\Effects\UpdateEffectPackageItemSlotService;
use App\Services\Effects\UpdateEffectPackageService;
use App\Services\X32\X32SceneMetadataService;
use App\Services\X32\X32SourceConnectivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConsoleController extends Controller
{
    use ResolvesBand;

    public function __construct(
        private readonly X32ConsoleLearningService $consoleLearningService,
        private readonly ShowConsoleBaselineService $showConsoleBaselineService,
        private readonly ShowConsoleParameterService $showConsoleParameterService,
        private readonly ShowConsoleControlService $showConsoleControlService,
        private readonly ShowConsoleMonitorSendControlService $showConsoleMonitorSendControlService,
        private readonly ShowConsoleMonitorBusEqControlService $showConsoleMonitorBusEqControlService,
        private readonly ShowConsoleMonitorBusMasterControlService $showConsoleMonitorBusMasterControlService,
        private readonly ShowConsoleStripEnricher $showConsoleStripEnricher,
        private readonly ShowConsoleWorkspaceResolver $workspaceResolver,
        private readonly VirtualConsoleStripBuilder $virtualConsoleStripBuilder,
        private readonly X32ConfigurationWorkspaceBuilder $configurationWorkspaceBuilder,
        private readonly X32MonitorsWorkspaceBuilder $monitorsWorkspaceBuilder,
        private readonly X32RoutingWorkspaceBuilder $routingWorkspaceBuilder,
        private readonly X32SourceConnectivityService $sourceConnectivityService,
        private readonly X32SceneMetadataService $sceneMetadataService,
        private readonly CreateEffectPackageService $createEffectPackageService,
        private readonly AddEffectToPackageService $addEffectToPackageService,
        private readonly UpdateEffectPackageService $updateEffectPackageService,
        private readonly DeleteEffectPackageService $deleteEffectPackageService,
        private readonly UpdateEffectPackageItemService $updateEffectPackageItemService,
        private readonly DeleteEffectPackageItemService $deleteEffectPackageItemService,
        private readonly UpdateEffectPackageItemParameterService $updateEffectPackageItemParameterService,
        private readonly UpdateEffectPackageItemSlotService $updateEffectPackageItemSlotService,
        private readonly UpdateEffectPackageItemRoutingPlanService $updateEffectPackageItemRoutingPlanService,
        private readonly EffectRoutingPlanSuggester $effectRoutingPlanSuggester,
        private readonly EffectPackageDeploymentPlanPreviewService $effectPackageDeploymentPlanPreviewService,
        private readonly DeployEffectPackageItemService $deployEffectPackageItemService,
    ) {}

    public function index(): View
    {
        $band = $this->band();

        return view('console.index', [
            'band' => $band,
            'recentSnapshots' => ConsoleLearningSnapshot::query()
                ->where('band_id', $band->id)
                ->with(['show', 'integrationDevice'])
                ->latest()
                ->limit(20)
                ->get(),
            'activeBaselines' => ShowConsoleBaseline::query()
                ->where('band_id', $band->id)
                ->where('active', true)
                ->with(['show', 'sourceSnapshot'])
                ->orderBy('saved_at', 'desc')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $band = $this->band();

        return view('console.learn', [
            'band' => $band,
            'show' => null,
            'shows' => $band->shows()->orderBy('name')->get(),
            'consoleDevices' => $this->consoleDevicesForBand($band),
            'hasActiveBaseline' => false,
        ]);
    }

    public function learnForShow(Show $show): View
    {
        $this->ensureShowBelongsToBand($show);

        return view('console.learn', [
            'band' => $this->band(),
            'show' => $show,
            'shows' => collect(),
            'consoleDevices' => $this->consoleDevicesForBand($show->band),
            'hasActiveBaseline' => $this->workspaceResolver->activeBaselineForShow($show) !== null,
        ]);
    }

    public function showForShow(Show $show): View|RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        return view('console.workspace', $this->workspaceViewData($show, $workspace));
    }

    public function configurationForShow(Show $show): View|RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $summary = $workspace['summary'];
        $baseline = $workspace['baseline'] ?? null;

        $consoleType = $baseline?->console_type
            ?? ConsoleType::tryFrom((string) ($summary['console_type'] ?? ConsoleType::X32->value))
            ?? ConsoleType::X32;

        $baseline?->loadMissing('sourceSnapshot.integrationDevice');

        $device = $baseline?->sourceSnapshot?->integrationDevice
            ?? $workspace['pendingSnapshot']?->integrationDevice;

        $summary = $this->sceneMetadataService->enrichSummaryWithSceneName($summary, $device);

        return view('console.configuration', [
            'band' => $this->band(),
            'show' => $show,
            'workspaceMode' => $workspace['mode'],
            'summary' => $summary,
            'consoleType' => $consoleType,
            'configurationWorkspace' => $this->configurationWorkspaceBuilder->build($summary),
        ]);
    }

    public function effectsForShow(Show $show): View|RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $summary = $workspace['summary'];
        $baseline = $workspace['baseline'] ?? null;

        $consoleType = $baseline?->console_type
            ?? ConsoleType::tryFrom((string) ($summary['console_type'] ?? ConsoleType::X32->value))
            ?? ConsoleType::X32;

        $baseline?->loadMissing('sourceSnapshot.integrationDevice');

        $device = $baseline?->sourceSnapshot?->integrationDevice;
        $summary = $this->sceneMetadataService->enrichSummaryWithSceneName($summary, $device);

        $packages = EffectPackage::query()
            ->where('is_active', true)
            ->with([
                'effectPackageTypeOption',
                'effectPackageItems.x32Effect',
                'effectPackageItems.effectDefinition',
                'effectPackageItems.parameters',
                'effectPackageItems.targetSections',
            ])
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $selectedPackageId = (int) (request()->query('package') ?: session('effects.selected_package_id'));
        $selectedPackage = $packages->firstWhere('id', $selectedPackageId) ?? $packages->first();

        return view('console.effects', [
            'band' => $this->band(),
            'show' => $show,
            'workspaceMode' => $workspace['mode'],
            'summary' => $summary,
            'consoleType' => $consoleType,
            'packages' => $packages,
            'selectedPackage' => $selectedPackage,
            'routingPlanSuggester' => $this->effectRoutingPlanSuggester,
            'slotAvailability' => app(EffectPackageItemSlotAvailabilityService::class),
            'deploymentPlan' => $this->effectPackageDeploymentPlanPreviewService->preview($selectedPackage),
            'effectDeployControl' => $this->deployEffectPackageItemService->controlContext($show),
        ]);
    }

    public function newEffectPackageForShow(Show $show): View|RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $summary = $workspace['summary'];
        $baseline = $workspace['baseline'] ?? null;

        $consoleType = $baseline?->console_type
            ?? ConsoleType::tryFrom((string) ($summary['console_type'] ?? ConsoleType::X32->value))
            ?? ConsoleType::X32;

        $baseline?->loadMissing('sourceSnapshot.integrationDevice');

        $device = $baseline?->sourceSnapshot?->integrationDevice;
        $summary = $this->sceneMetadataService->enrichSummaryWithSceneName($summary, $device);

        return view('console.effects-new-package', [
            'band' => $this->band(),
            'show' => $show,
            'workspaceMode' => $workspace['mode'],
            'summary' => $summary,
            'consoleType' => $consoleType,
            'packageTypes' => EffectPackageTypeOption::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(),
            'x32Effects' => X32Effect::query()
                ->where('is_active', true)
                ->orderBy('x32_slot_group')
                ->orderBy('x32_algorithm_id')
                ->orderBy('effect_name')
                ->get(),
        ]);
    }

    public function storeEffectPackageForShow(StoreConsoleEffectPackageRequest $request, Show $show): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $package = $this->createEffectPackageService->create($request->validated());

        return redirect()
            ->route('shows.console.effects', ['show' => $show, 'package' => $package->id])
            ->with('effects.selected_package_id', $package->id);
    }

    public function addEffectToPackageForShow(Show $show, EffectPackage $package): View|RedirectResponse
    {
        $workspaceData = $this->resolveEffectsWorkspaceData($show);

        if ($workspaceData instanceof RedirectResponse) {
            return $workspaceData;
        }

        $package->loadMissing(['effectPackageTypeOption', 'effectPackageItems']);

        $x32Effects = X32Effect::query()
            ->where('is_active', true)
            ->orderBy('x32_slot_group')
            ->orderBy('x32_algorithm_id')
            ->orderBy('effect_name')
            ->get();

        $unavailableSlots = collect(range(1, 8))
            ->mapWithKeys(function (int $slot) use ($package) {
                $reason = app(EffectPackageItemSlotAvailabilityService::class)->reasonForNewItemSlot($package, $slot);

                return $reason === null ? [] : [$slot => $reason];
            });

        return view('console.effects-add-effect', array_merge($workspaceData, [
            'package' => $package,
            'x32Effects' => $x32Effects,
            'unavailableSlots' => $unavailableSlots,
        ]));
    }

    public function storeEffectToPackageForShow(
        StoreAddEffectToPackageRequest $request,
        Show $show,
        EffectPackage $package,
    ): RedirectResponse {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $validated = $request->validated();
        $validated['preferred_slot_number'] = $request->input('preferred_slot_number') === null
            || $request->input('preferred_slot_number') === ''
            ? null
            : (int) $validated['preferred_slot_number'];

        try {
            $this->addEffectToPackageService->add($package, $validated);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('shows.console.effects', ['show' => $show, 'package' => $package->id])
            ->with('effects.selected_package_id', $package->id);
    }

    public function editEffectPackageForShow(Show $show, EffectPackage $package): View|RedirectResponse
    {
        $workspaceData = $this->resolveEffectsWorkspaceData($show);

        if ($workspaceData instanceof RedirectResponse) {
            return $workspaceData;
        }

        return view('console.effects-edit-package', array_merge($workspaceData, [
            'package' => $package->loadMissing('effectPackageTypeOption'),
            'packageTypes' => EffectPackageTypeOption::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(),
        ]));
    }

    public function updateEffectPackageForShow(
        UpdateConsoleEffectPackageRequest $request,
        Show $show,
        EffectPackage $package,
    ): RedirectResponse {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        try {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            $updated = $this->updateEffectPackageService->update($package, $data);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('shows.console.effects', ['show' => $show, 'package' => $updated->id])
            ->with('effects.selected_package_id', $updated->id);
    }

    public function destroyEffectPackageForShow(Show $show, EffectPackage $package): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $this->deleteEffectPackageService->delete($package);

        return redirect()
            ->route('shows.console.effects', $show)
            ->with('status', 'Effect package deleted.');
    }

    public function editEffectPackageItemForShow(Show $show, EffectPackageItem $item): View|RedirectResponse
    {
        $workspaceData = $this->resolveEffectsWorkspaceData($show);

        if ($workspaceData instanceof RedirectResponse) {
            return $workspaceData;
        }

        $item->loadMissing([
            'effectPackage',
            'x32Effect',
            'effectDefinition',
            'parameters',
            'targetSections',
        ]);

        return view('console.effects-edit-package-item', array_merge($workspaceData, [
            'item' => $item,
            'package' => $item->effectPackage,
            'slotAvailability' => app(EffectPackageItemSlotAvailabilityService::class),
            'routingModes' => EffectRoutingMode::cases(),
            'returnDestinations' => EffectReturnDestination::cases(),
            'targetSectionOptions' => EffectRoutingTargetSection::selectableCases(),
        ]));
    }

    public function updateEffectPackageItemForShow(
        UpdateConsoleEffectPackageItemRequest $request,
        Show $show,
        EffectPackageItem $item,
    ): RedirectResponse {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $validated = $request->validated();
        $validated['preferred_slot_number'] = $request->input('preferred_slot_number') === null
            || $request->input('preferred_slot_number') === ''
            ? null
            : (int) $validated['preferred_slot_number'];
        $validated['target_sections'] = $request->input('target_sections', []);

        try {
            $updated = $this->updateEffectPackageItemService->update($item, $validated);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('shows.console.effects', ['show' => $show, 'package' => $updated->effect_package_id])
            ->with('effects.selected_package_id', $updated->effect_package_id);
    }

    public function destroyEffectPackageItemForShow(Show $show, EffectPackageItem $item): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $packageId = $this->deleteEffectPackageItemService->delete($item);

        return redirect()
            ->route('shows.console.effects', ['show' => $show, 'package' => $packageId])
            ->with('effects.selected_package_id', $packageId)
            ->with('status', 'Package effect removed.');
    }

    public function updateEffectPackageParameter(Request $request, Show $show, EffectPackageItemParameter $parameter): JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'value' => ['nullable', 'string', 'max:6'],
        ]);

        $updated = $this->updateEffectPackageItemParameterService->update(
            $parameter,
            $validated['value'] ?? null,
        );

        return response()->json([
            'parameter' => [
                'id' => $updated->id,
                'value' => $updated->value,
            ],
        ]);
    }

    public function updateEffectPackageItemSlot(Request $request, Show $show, EffectPackageItem $item): JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'preferred_slot_number' => ['nullable', 'integer', 'min:1', 'max:8'],
        ]);

        $item->loadMissing(['x32Effect', 'effectDefinition']);

        try {
            $updated = $this->updateEffectPackageItemSlotService->update(
                $item,
                $validated['preferred_slot_number'] ?? null,
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'item' => [
                'id' => $updated->id,
                'preferred_slot_number' => $updated->preferred_slot_number,
            ],
        ]);
    }

    public function deployEffectPackageItem(Show $show, EffectPackageItem $item): JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        $result = $this->deployEffectPackageItemService->deploy($show, $item);

        return response()->json($result);
    }

    public function updateEffectPackageItemRoutingPlan(Request $request, Show $show, EffectPackageItem $item): JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        try {
            $validated = $request->validate([
                'routing_mode' => ['nullable', 'string'],
                'target_sections' => ['nullable', 'array'],
                'target_sections.*' => ['string', 'distinct', 'in:'.implode(',', EffectRoutingTargetSection::selectableValues())],
                'return_destination' => ['nullable', 'string'],
                'default_return_level' => ['nullable', 'numeric'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        $item->loadMissing(['x32Effect', 'effectDefinition', 'targetSections']);

        try {
            $updated = $this->updateEffectPackageItemRoutingPlanService->update($item, $validated);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'item' => [
                'id' => $updated->id,
                'routing_mode' => $updated->routing_mode?->value,
                'routing_mode_label' => $updated->routingModeLabel(),
                'target_sections' => $updated->selectedTargetSectionValues(),
                'target_sections_label' => $updated->routingTargetSectionsSummary(),
                'target_section_labels' => $updated->selectedTargetSectionLabels(),
                'return_destination' => $updated->return_destination?->value,
                'return_destination_label' => $updated->returnDestinationLabel(),
                'default_return_level' => $updated->default_return_level,
                'default_return_level_label' => $updated->formattedDefaultReturnLevel(),
                'notes' => $updated->notes,
            ],
        ]);
    }

    public function busLayoutForShow(Request $request, Show $show, int $bus): View|RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        abort_unless($bus >= 1 && $bus <= 16, 404);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $summary = $workspace['summary'];
        $baseline = $workspace['baseline'] ?? null;

        $consoleType = $baseline?->console_type
            ?? ConsoleType::tryFrom((string) ($summary['console_type'] ?? ConsoleType::X32->value))
            ?? ConsoleType::X32;

        $baseline?->loadMissing('sourceSnapshot.integrationDevice');

        $device = $baseline?->sourceSnapshot?->integrationDevice;
        $summary = $this->sceneMetadataService->enrichSummaryWithSceneName($summary, $device);

        $selectedChannel = $request->query('channel');
        $selectedChannel = is_numeric($selectedChannel) ? (int) $selectedChannel : null;

        try {
            $monitorsWorkspace = $this->monitorsWorkspaceBuilder->build($summary, $bus, $selectedChannel);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        return view('console.monitors', [
            'band' => $this->band(),
            'show' => $show,
            'workspaceMode' => $workspace['mode'],
            'summary' => $summary,
            'consoleType' => $consoleType,
            'monitorsWorkspace' => $monitorsWorkspace,
            'monitorSendControl' => array_merge(
                $this->showConsoleMonitorSendControlService->controlContext($show),
                [
                    'update_url' => route('shows.console.bus.sends.update', [$show, $bus]),
                    'bus_number' => $bus,
                ],
            ),
            'monitorEqControl' => array_merge(
                $this->showConsoleMonitorBusEqControlService->controlContext($show),
                [
                    'update_url' => route('shows.console.bus.eq.update', [$show, $bus]),
                    'bus_number' => $bus,
                ],
            ),
            'monitorBusMasterControl' => array_merge(
                $this->showConsoleMonitorBusMasterControlService->controlContext($show),
                [
                    'update_url' => route('shows.console.bus.master.update', [$show, $bus]),
                    'bus_number' => $bus,
                ],
            ),
        ]);
    }

    public function updateMonitorBusMaster(Request $request, Show $show, int $bus): JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        abort_unless($bus >= 1 && $bus <= 16, 404);

        $validated = $request->validate([
            'parameter' => ['required', 'string', 'in:level,mute'],
            'value' => ['required'],
        ]);

        $result = $this->showConsoleMonitorBusMasterControlService->updateMaster(
            $show,
            $bus,
            $validated['parameter'],
            $validated['value'],
        );

        return response()->json($result);
    }

    public function updateMonitorEq(Request $request, Show $show, int $bus): JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        abort_unless($bus >= 1 && $bus <= 16, 404);

        $validated = $request->validate([
            'parameter' => ['required', 'string', 'in:on,type,f,g,q'],
            'band' => ['nullable', 'integer', 'min:1', 'max:6'],
            'value' => ['required'],
        ]);

        if ($validated['parameter'] === 'on' && array_key_exists('band', $validated) && $validated['band'] !== null) {
            throw ValidationException::withMessages([
                'band' => 'Band number is not used for bus EQ master on.',
            ]);
        }

        if ($validated['parameter'] !== 'on' && empty($validated['band'])) {
            throw ValidationException::withMessages([
                'band' => 'Band number is required for this EQ parameter.',
            ]);
        }

        $result = $this->showConsoleMonitorBusEqControlService->updateEq(
            $show,
            $bus,
            isset($validated['band']) ? (int) $validated['band'] : null,
            $validated['parameter'],
            $validated['value'],
        );

        return response()->json($result);
    }

    public function updateMonitorSend(Request $request, Show $show, int $bus): JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        abort_unless($bus >= 1 && $bus <= 16, 404);

        $validated = $request->validate([
            'channel' => ['required', 'integer', 'min:1', 'max:32'],
            'parameter' => ['required', 'string', 'in:level,mute'],
            'value' => ['required'],
        ]);

        $result = $this->showConsoleMonitorSendControlService->updateSend(
            $show,
            $bus,
            (int) $validated['channel'],
            $validated['parameter'],
            $validated['value'],
        );

        return response()->json($result);
    }

    public function redirectLegacyMonitorRoute(Show $show, int $busNumber): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        abort_unless($busNumber >= 1 && $busNumber <= 16, 404);

        return redirect()->route('shows.console.bus.layout', [$show, $busNumber]);
    }

    public function monitorsForShow(Show $show, int $busNumber): View|RedirectResponse
    {
        return $this->redirectLegacyMonitorRoute($show, $busNumber);
    }

    public function routingForShow(Show $show): View|RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $summary = $workspace['summary'];
        $baseline = $workspace['baseline'] ?? null;

        $consoleType = $baseline?->console_type
            ?? ConsoleType::tryFrom((string) ($summary['console_type'] ?? ConsoleType::X32->value))
            ?? ConsoleType::X32;

        $baseline?->loadMissing('sourceSnapshot.integrationDevice');

        $device = $baseline?->sourceSnapshot?->integrationDevice;
        $summary = $this->sourceConnectivityService->enrichSummaryWithLiveConnectivity($summary, $device);
        $summary = $this->sceneMetadataService->enrichSummaryWithSceneName($summary, $device);

        return view('console.routing', [
            'band' => $this->band(),
            'show' => $show,
            'workspaceMode' => $workspace['mode'],
            'summary' => $summary,
            'consoleType' => $consoleType,
            'routingFlow' => $this->routingWorkspaceBuilder->buildRoutingFlowRow($summary),
            'routingDetail' => $this->routingWorkspaceBuilder->buildConfigurationDetailRow($summary, [
                'baseline_name' => $baseline?->baseline_name,
                'baseline_saved_at' => $baseline?->saved_at,
                'device_name' => $baseline?->sourceSnapshot?->integrationDevice?->name,
                'requested_scene_number' => $baseline?->sourceSnapshot?->requested_scene_number,
            ]),
            'routingBottom' => $this->routingWorkspaceBuilder->buildRoutingBottomRow([
                'learn_url' => route('shows.console.learn', $show),
                'routing' => is_array($summary['routing'] ?? null) ? $summary['routing'] : [],
            ]),
        ]);
    }

    public function saveForShow(Request $request, Show $show): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $pendingSnapshot = $this->workspaceResolver->pendingSnapshotForShow($show);

        if ($pendingSnapshot === null) {
            return redirect()
                ->route('shows.console', $show)
                ->with('status', 'Nothing to save — learn a scene first.');
        }

        $validated = $request->validate([
            'baseline_name' => ['nullable', 'string', 'max:255'],
        ]);

        $this->showConsoleBaselineService->saveFromSnapshot(
            $pendingSnapshot,
            $validated['baseline_name'] ?? null,
        );

        return redirect()
            ->route('shows.console', $show)
            ->with('status', 'Show console saved.');
    }

    public function updateParameter(Request $request, Show $show): JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'osc_path' => ['required', 'string', 'max:128'],
            'parameter' => ['required', 'string', 'in:fader,mute'],
            'value' => ['required'],
        ]);

        $result = $this->showConsoleParameterService->updateParameter(
            $show,
            $validated['osc_path'],
            $validated['parameter'],
            $validated['value'],
        );

        return response()->json([
            'ok' => true,
            ...$result,
        ]);
    }

    public function updateControl(Request $request, Show $show): JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'control_key' => ['required', 'string', 'max:64'],
            'channel' => ['required', 'integer', 'min:1', 'max:32'],
            'value' => ['required'],
        ]);

        $result = $this->showConsoleControlService->updateControl(
            $show,
            (int) $validated['channel'],
            $validated['control_key'],
            $validated['value'],
        );

        return response()->json([
            'ok' => true,
            ...$result,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate([
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'integration_device_id' => ['required', 'integer', 'exists:integration_devices,id'],
            'requested_scene_number' => ['required', 'string', 'max:8'],
        ]);

        $show = Show::query()->findOrFail($validated['show_id']);
        $this->ensureShowBelongsToBand($show);

        $device = IntegrationDevice::query()->findOrFail($validated['integration_device_id']);
        abort_unless($device->band_id === $band->id, 404);

        return $this->processLearning($show, $device, $validated['requested_scene_number']);
    }

    public function storeForShow(Request $request, Show $show): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'integration_device_id' => ['required', 'integer', 'exists:integration_devices,id'],
            'requested_scene_number' => ['required', 'string', 'max:8'],
        ]);

        $device = IntegrationDevice::query()->findOrFail($validated['integration_device_id']);
        abort_unless($device->band_id === $show->band_id, 404);

        return $this->processLearning($show, $device, $validated['requested_scene_number']);
    }

    public function showSnapshot(ConsoleLearningSnapshot $snapshot): RedirectResponse
    {
        $this->ensureBandOwns($snapshot);

        return redirect()->route('shows.console', $snapshot->show_id);
    }

    public function saveBaseline(Request $request, ConsoleLearningSnapshot $snapshot): RedirectResponse
    {
        $this->ensureBandOwns($snapshot);

        abort_unless($snapshot->learning_status === ConsoleLearningStatus::Review, 404);

        $validated = $request->validate([
            'baseline_name' => ['nullable', 'string', 'max:255'],
        ]);

        $this->showConsoleBaselineService->saveFromSnapshot(
            $snapshot,
            $validated['baseline_name'] ?? null,
        );

        return redirect()
            ->route('shows.console', $snapshot->show_id)
            ->with('status', 'Show console saved.');
    }

    public function showBaseline(ShowConsoleBaseline $baseline): View
    {
        $this->ensureBandOwns($baseline);

        $baseline->load(['show', 'sourceSnapshot.integrationDevice']);

        $summary = $baseline->baseline_json ?? [];

        return view('console.baseline', [
            'band' => $this->band(),
            'baseline' => $baseline,
            'summary' => $summary,
            'channels' => $summary['channels'] ?? [],
            'buses' => $summary['buses'] ?? [],
            'dcas' => $summary['dcas'] ?? [],
            'matrices' => $summary['matrices'] ?? [],
            'fx' => $summary['fx'] ?? [],
        ]);
    }

    /**
     * @param  array{
     *     mode: string,
     *     pendingSnapshot?: ConsoleLearningSnapshot,
     *     baseline?: ShowConsoleBaseline,
     *     summary: array<string, mixed>,
     *     sourceSnapshot?: ConsoleLearningSnapshot|null
     * }  $workspace
     * @return array<string, mixed>
     */
    private function workspaceViewData(Show $show, array $workspace): array
    {
        $summary = $workspace['summary'];
        $sourceSnapshot = $workspace['sourceSnapshot'] ?? null;
        $baseline = $workspace['baseline'] ?? null;
        $pendingSnapshot = $workspace['pendingSnapshot'] ?? null;

        $channels = $this->showConsoleStripEnricher->enrich(
            $summary['channels'] ?? [],
            'channel',
            $sourceSnapshot,
        );

        $virtualStrips = array_map(
            static fn ($strip) => $strip->toArray(),
            $this->virtualConsoleStripBuilder->build($summary, $sourceSnapshot),
        );

        $consoleType = $baseline?->console_type
            ?? ConsoleType::tryFrom((string) ($summary['console_type'] ?? ConsoleType::X32->value))
            ?? ConsoleType::X32;

        $defaultBaselineName = sprintf(
            'Scene %s — %s',
            $summary['scene_number'] ?? $pendingSnapshot?->requested_scene_number ?? $sourceSnapshot?->requested_scene_number ?? '—',
            $summary['device_name'] ?? $pendingSnapshot?->integrationDevice?->name ?? 'Console',
        );

        return [
            'band' => $this->band(),
            'show' => $show,
            'workspaceMode' => $workspace['mode'],
            'baseline' => $baseline,
            'pendingSnapshot' => $pendingSnapshot,
            'summary' => $summary,
            'consoleType' => $consoleType,
            'defaultBaselineName' => $defaultBaselineName,
            'channels' => $channels,
            'virtualStrips' => $virtualStrips,
            'controlUpdateUrl' => route('shows.console.controls.update', $show),
            'buses' => $this->showConsoleStripEnricher->enrich($summary['buses'] ?? [], 'bus', $sourceSnapshot),
            'dcas' => $this->showConsoleStripEnricher->enrich($summary['dcas'] ?? [], 'dca', $sourceSnapshot),
            'matrices' => $this->showConsoleStripEnricher->enrich($summary['matrices'] ?? [], 'matrix', $sourceSnapshot),
            'fx' => $summary['fx'] ?? [],
            'routing' => $summary['routing'] ?? [],
            'consoleMetadataIncomplete' => $this->showConsoleStripEnricher->metadataIncomplete($channels),
        ];
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    private function resolveEffectsWorkspaceData(Show $show): array|RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $workspace = $this->workspaceResolver->resolve($show);

        if ($workspace['mode'] === ShowConsoleWorkspaceResolver::MODE_EMPTY) {
            return redirect()->route('shows.console.learn', $show);
        }

        $summary = $workspace['summary'];
        $baseline = $workspace['baseline'] ?? null;

        $consoleType = $baseline?->console_type
            ?? ConsoleType::tryFrom((string) ($summary['console_type'] ?? ConsoleType::X32->value))
            ?? ConsoleType::X32;

        $baseline?->loadMissing('sourceSnapshot.integrationDevice');

        $device = $baseline?->sourceSnapshot?->integrationDevice;
        $summary = $this->sceneMetadataService->enrichSummaryWithSceneName($summary, $device);

        return [
            'band' => $this->band(),
            'show' => $show,
            'workspaceMode' => $workspace['mode'],
            'summary' => $summary,
            'consoleType' => $consoleType,
        ];
    }

    private function processLearning(
        Show $show,
        IntegrationDevice $device,
        string $requestedSceneNumber,
    ): RedirectResponse {
        $snapshot = $this->consoleLearningService->startLearning(
            $show,
            $device,
            $requestedSceneNumber,
        );

        if ($snapshot->learning_status === ConsoleLearningStatus::Failed) {
            return redirect()
                ->route('shows.console.learn', $show)
                ->with('status', 'Console learning failed.')
                ->with('learning_errors', $snapshot->errors_json ?? []);
        }

        return redirect()
            ->route('shows.console', $show)
            ->with('status', 'Scene loaded — review the console and save when ready.');
    }

    private function consoleDevicesForBand($band)
    {
        return IntegrationDevice::query()
            ->where('band_id', $band->id)
            ->where('device_type', IntegrationDevice::TYPE_X32)
            ->where('enabled', true)
            ->with('integrationConnectionProfiles')
            ->orderBy('name')
            ->get();
    }
}
