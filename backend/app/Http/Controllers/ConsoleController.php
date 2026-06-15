<?php

namespace App\Http\Controllers;

use App\Enums\ConsoleLearningStatus;
use App\Enums\ConsoleType;
use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\Console\ShowConsoleBaselineService;
use App\Services\Console\ShowConsoleControlService;
use App\Services\Console\ShowConsoleParameterService;
use App\Services\Console\ShowConsoleStripEnricher;
use App\Services\Console\ShowConsoleWorkspaceResolver;
use App\Services\Console\VirtualConsoleStripBuilder;
use App\Services\Console\X32ConsoleLearningService;
use App\Services\Console\X32RoutingWorkspaceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsoleController extends Controller
{
    use ResolvesBand;

    public function __construct(
        private readonly X32ConsoleLearningService $consoleLearningService,
        private readonly ShowConsoleBaselineService $showConsoleBaselineService,
        private readonly ShowConsoleParameterService $showConsoleParameterService,
        private readonly ShowConsoleControlService $showConsoleControlService,
        private readonly ShowConsoleStripEnricher $showConsoleStripEnricher,
        private readonly ShowConsoleWorkspaceResolver $workspaceResolver,
        private readonly VirtualConsoleStripBuilder $virtualConsoleStripBuilder,
        private readonly X32RoutingWorkspaceBuilder $routingWorkspaceBuilder,
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
            ]),
            'routingBottom' => $this->routingWorkspaceBuilder->buildRoutingBottomRow([
                'learn_url' => route('shows.console.learn', $show),
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
