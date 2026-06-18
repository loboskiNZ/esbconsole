<?php

namespace App\Services\Effects;

use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Models\ConsoleLearningSnapshot;
use App\Models\EffectPackageItem;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\Console\ShowConsoleWorkspaceResolver;
use App\Services\X32\X32EffectParameterOscEncoder;
use App\Services\X32\X32FxOscDeployReadback;
use App\Services\X32\X32OscAddressMap;
use App\Services\X32\X32RuntimeModeResolver;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeployEffectPackageItemService
{
    private readonly X32FxOscDeployReadback $fxOscDeployReadback;

    public function __construct(
        private readonly ShowConsoleWorkspaceResolver $workspaceResolver,
        private readonly X32OscConsoleClientInterface $oscClient,
        private readonly X32RuntimeModeResolver $runtimeModeResolver,
        private readonly X32EffectParameterOscEncoder $parameterEncoder,
    ) {
        $this->fxOscDeployReadback = new X32FxOscDeployReadback($this->oscClient, $this->parameterEncoder);
    }

    /**
     * @return array{available: bool, reason: ?string}
     */
    public function controlContext(Show $show): array
    {
        try {
            $device = $this->resolveDeviceForShow($show);
            $runtimeMode = $this->runtimeModeResolver->resolve($device->configuration ?? []);

            if (! $this->runtimeModeResolver->isLive($runtimeMode)) {
                return [
                    'available' => false,
                    'reason' => 'Live X32 control is not enabled for this console device.',
                ];
            }

            $this->resolveOscProfile($device);

            return [
                'available' => true,
                'reason' => null,
            ];
        } catch (ValidationException $exception) {
            return [
                'available' => false,
                'reason' => collect($exception->errors())->flatten()->first() ?? 'Effect deploy is unavailable.',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function deploy(Show $show, EffectPackageItem $item): array
    {
        $item->loadMissing(['x32Effect', 'effectDefinition', 'parameters']);

        $slot = $item->preferred_slot_number;

        if ($slot === null) {
            return $this->failurePayload(
                $item,
                null,
                null,
                [],
                'Effect is not allocated to an FX slot.',
            );
        }

        $algorithmId = $item->x32Effect?->x32_algorithm_id ?? $item->effectDefinition?->x32_algorithm_id;
        $effectCode = $item->x32Effect?->effect_code ?? $item->effectDefinition?->x32_algorithm_code;

        if ($algorithmId === null) {
            return $this->failurePayload(
                $item,
                $slot,
                null,
                [],
                'Effect algorithm ID is not available for deployment.',
            );
        }

        try {
            $device = $this->resolveDeviceForShow($show);
            $profile = $this->resolveOscProfile($device);
        } catch (ValidationException $exception) {
            return $this->failurePayload(
                $item,
                $slot,
                null,
                [],
                collect($exception->errors())->flatten()->first() ?? 'Effect deploy is unavailable.',
            );
        }

        $host = $profile->host ?? '127.0.0.1';
        $port = (int) ($profile->port ?? 10023);
        $runtimeMode = $this->runtimeModeResolver->resolve($device->configuration ?? []);

        if (! $this->runtimeModeResolver->isLive($runtimeMode)) {
            return $this->failurePayload(
                $item,
                $slot,
                null,
                [],
                'Live X32 control is not enabled for this console device.',
            );
        }

        $typePath = $this->fxOscDeployReadback->resolveTypePath($host, $port, $slot);
        $parameterPaths = [];

        try {
            $this->oscClient->setInt($host, $port, $typePath, (int) $algorithmId);

            if (! $this->fxOscDeployReadback->confirmType($host, $port, $typePath, (int) $algorithmId, $effectCode)) {
                return $this->failurePayload(
                    $item,
                    $slot,
                    $typePath,
                    [],
                    'Effect type write was not confirmed by the console.',
                    (int) $algorithmId,
                    null,
                );
            }

            foreach ($item->parameters->sortBy('parameter_number') as $parameter) {
                if ($parameter->value === null || $parameter->value === '') {
                    continue;
                }

                $oscValue = $this->parameterEncoder->encode($parameter);
                $parameterPath = $this->fxOscDeployReadback->resolveParameterPath(
                    $host,
                    $port,
                    $slot,
                    $parameter->parameter_number,
                );

                if ($parameter->value_type === 'enum') {
                    $this->oscClient->setInt($host, $port, $parameterPath, $this->parameterEncoder->encodeInt($parameter));
                } else {
                    $this->oscClient->setFloat($host, $port, $parameterPath, $oscValue);
                }

                if (! $this->fxOscDeployReadback->confirmParameter($host, $port, $parameterPath, $parameter, $oscValue)) {
                    return $this->failurePayload(
                        $item,
                        $slot,
                        $parameterPath,
                        array_merge([$typePath], $parameterPaths),
                        sprintf(
                            'Effect parameter %d write was not confirmed by the console.',
                            $parameter->parameter_number,
                        ),
                        $oscValue,
                        null,
                    );
                }

                $parameterPaths[] = $parameterPath;
            }
        } catch (ValidationException $exception) {
            return $this->failurePayload(
                $item,
                $slot,
                $typePath,
                $parameterPaths,
                collect($exception->errors())->flatten()->first() ?? 'Effect deploy failed.',
            );
        } catch (Throwable $exception) {
            return $this->failurePayload(
                $item,
                $slot,
                $typePath,
                $parameterPaths,
                'Effect deploy failed: '.$exception->getMessage(),
            );
        }

        return $this->successPayload($item, $slot, $typePath, $parameterPaths, (int) $algorithmId);
    }

    /**
     * @param  list<string>  $parameterPaths
     * @return array<string, mixed>
     */
    private function successPayload(
        EffectPackageItem $item,
        int $slot,
        string $typePath,
        array $parameterPaths,
        int $algorithmId,
    ): array {
        return [
            'success' => true,
            'status' => 'deployed',
            'item_id' => $item->id,
            'slot' => $slot,
            'slot_label' => 'FX'.$slot,
            'effect_name' => $this->displayName($item),
            'algorithm_id' => $algorithmId,
            'osc_paths' => [
                'type' => $typePath,
                'parameters' => $parameterPaths,
            ],
            'error' => null,
        ];
    }

    /**
     * @param  list<string>  $parameterPaths
     * @return array<string, mixed>
     */
    private function failurePayload(
        EffectPackageItem $item,
        ?int $slot,
        ?string $oscPath,
        array $parameterPaths,
        string $error,
        mixed $requestedValue = null,
        mixed $confirmedValue = null,
    ): array {
        $typePath = $slot !== null ? X32OscAddressMap::fxType($slot) : null;

        return [
            'success' => false,
            'status' => 'failed',
            'item_id' => $item->id,
            'slot' => $slot,
            'slot_label' => $slot !== null ? 'FX'.$slot : null,
            'effect_name' => $this->displayName($item),
            'osc_path' => $oscPath,
            'osc_paths' => [
                'type' => $typePath,
                'parameters' => $parameterPaths,
            ],
            'requested_value' => $requestedValue,
            'confirmed_value' => $confirmedValue,
            'error' => $error,
        ];
    }

    private function displayName(EffectPackageItem $item): string
    {
        return $item->x32Effect?->displayName()
            ?? $item->effectDefinition?->name
            ?? 'Effect';
    }

    private function resolveDeviceForShow(Show $show): IntegrationDevice
    {
        $pendingSnapshot = $this->workspaceResolver->pendingSnapshotForShow($show);

        if ($pendingSnapshot !== null) {
            return $this->resolveDeviceFromSnapshot($pendingSnapshot);
        }

        $baseline = $this->workspaceResolver->activeBaselineForShow($show);

        if ($baseline === null) {
            throw ValidationException::withMessages([
                'show' => 'No console data to update — learn a scene first.',
            ]);
        }

        return $this->resolveDeviceFromBaseline($baseline);
    }

    private function resolveDeviceFromSnapshot(ConsoleLearningSnapshot $snapshot): IntegrationDevice
    {
        $device = $snapshot->integrationDevice;

        if ($device === null || ! $device->enabled) {
            throw ValidationException::withMessages([
                'console' => 'Console device is not available for this learning snapshot.',
            ]);
        }

        return $device;
    }

    private function resolveDeviceFromBaseline(ShowConsoleBaseline $baseline): IntegrationDevice
    {
        $device = $baseline->sourceSnapshot?->integrationDevice;

        if ($device === null || ! $device->enabled) {
            throw ValidationException::withMessages([
                'console' => 'Console device is not available for this show baseline.',
            ]);
        }

        return $device;
    }

    private function resolveOscProfile(IntegrationDevice $device): IntegrationConnectionProfile
    {
        $profile = $device->integrationConnectionProfiles()
            ->where('enabled', true)
            ->where('protocol', IntegrationConnectionProfile::PROTOCOL_OSC)
            ->orderBy('id')
            ->first();

        if ($profile === null) {
            throw ValidationException::withMessages([
                'console' => 'No enabled OSC connection profile is configured for this console device.',
            ]);
        }

        return $profile;
    }
}
