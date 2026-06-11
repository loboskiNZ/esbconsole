<?php

namespace App\Services\X32;

use App\Contracts\X32\UdpSocketSenderInterface;
use App\Services\Integration\IntegrationDeviceRegistry;
use App\Services\Integration\IntegrationDeviceValidator;
use App\Services\Runtime\Adapters\X32AdapterFactory;

class X32LiveValidationService
{
    public function __construct(
        private readonly X32DeviceSelector $deviceSelector,
        private readonly X32RuntimeModeResolver $runtimeModeResolver,
        private readonly X32SceneParameterResolver $sceneParameterResolver,
        private readonly IntegrationDeviceValidator $deviceValidator,
        private readonly ?UdpSocketSenderInterface $socketSender = null,
    ) {}

    public function validate(
        int $bandId,
        string $scene,
        bool $confirmLive,
        ?string $deviceKey = null,
        ?string $operatorLabel = null,
        ?string $notes = null,
        ?int $performanceId = null,
    ): X32LiveValidationResult {
        $baseContext = array_filter([
            'operator_label' => $operatorLabel,
            'notes' => $notes,
            'action_type' => 'X32_SCENE',
        ], fn ($value) => $value !== null && $value !== '');

        if (! $confirmLive) {
            return $this->blocked(
                bandId: $bandId,
                deviceKey: $deviceKey,
                scene: $scene,
                message: 'Live X32 validation requires confirm_live=true.',
                context: array_merge($baseContext, ['gate' => 'confirm_live']),
            );
        }

        $normalizedScene = $this->sceneParameterResolver->resolve([
            'parameters' => ['scene' => $scene],
        ]);

        if ($normalizedScene === null) {
            return $this->blocked(
                bandId: $bandId,
                deviceKey: $deviceKey,
                scene: $scene,
                message: 'Scene must be a valid X32 scene number.',
                context: array_merge($baseContext, ['gate' => 'scene']),
            );
        }

        $selection = $this->deviceSelector->select(
            bandId: $bandId,
            performanceId: $performanceId,
            deviceKey: $deviceKey,
        );

        if ($selection === null) {
            return $this->blocked(
                bandId: $bandId,
                deviceKey: $deviceKey,
                scene: $normalizedScene,
                message: 'No enabled X32 integration device is available for validation.',
                context: array_merge($baseContext, ['gate' => 'device']),
            );
        }

        $device = $selection->device;
        $selectionContext = $selection->toContext();

        $runtimeMode = $this->runtimeModeResolver->resolve($device->configuration);

        if ($runtimeMode !== X32RuntimeModeResolver::MODE_LIVE) {
            return $this->blocked(
                bandId: $bandId,
                deviceKey: $device->device_key,
                scene: $normalizedScene,
                message: 'X32 device runtime_mode must be live for validation.',
                mode: $runtimeMode,
                context: array_merge($baseContext, $selectionContext, [
                    'gate' => 'runtime_mode',
                    'runtime_mode' => $runtimeMode,
                ]),
            );
        }

        $device->loadMissing('integrationConnectionProfiles');

        $profile = $device->integrationConnectionProfiles
            ->where('enabled', true)
            ->first();

        if ($profile === null) {
            return $this->blocked(
                bandId: $bandId,
                deviceKey: $device->device_key,
                scene: $normalizedScene,
                message: 'X32 validation requires an enabled connection profile.',
                mode: $runtimeMode,
                context: array_merge($baseContext, $selectionContext, ['gate' => 'connection_profile']),
            );
        }

        $profileValidation = $this->deviceValidator->validateProfile($profile);

        if (! $profileValidation->success) {
            return $this->blocked(
                bandId: $bandId,
                deviceKey: $device->device_key,
                scene: $normalizedScene,
                message: $profileValidation->message ?? 'Connection profile is invalid for live validation.',
                mode: $runtimeMode,
                context: array_merge($baseContext, $selectionContext, [
                    'gate' => 'connection_profile',
                    'profile_name' => $profile->profile_name,
                    'profile_status' => $profileValidation->status,
                ]),
            );
        }

        $adapter = X32AdapterFactory::createProduction($this->socketSender);

        $transportResult = $adapter->recallSceneCommand(new X32SceneRecallCommand(
            scene: $normalizedScene,
            deviceKey: $device->device_key,
            profileName: $profile->profile_name,
            protocol: $profile->protocol,
            host: $profile->host,
            port: $profile->port,
            dryRun: false,
            runtimeMode: X32RuntimeModeResolver::MODE_LIVE,
        ));

        if (! $transportResult->success) {
            return new X32LiveValidationResult(
                success: false,
                status: X32LiveValidationResult::STATUS_FAILED,
                message: $transportResult->message,
                bandId: $bandId,
                deviceKey: $device->device_key,
                scene: $normalizedScene,
                mode: $transportResult->mode,
                context: array_merge($baseContext, $selectionContext, $transportResult->context),
                occurredAt: now(),
            );
        }

        return new X32LiveValidationResult(
            success: true,
            status: X32LiveValidationResult::STATUS_ACKNOWLEDGED,
            message: $transportResult->message,
            bandId: $bandId,
            deviceKey: $device->device_key,
            scene: $normalizedScene,
            mode: $transportResult->mode,
            context: array_merge($baseContext, $selectionContext, $transportResult->context),
            occurredAt: now(),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function blocked(
        int $bandId,
        ?string $deviceKey,
        ?string $scene,
        string $message,
        array $context = [],
        string $mode = X32RuntimeModeResolver::MODE_DRY_RUN,
    ): X32LiveValidationResult {
        return new X32LiveValidationResult(
            success: false,
            status: X32LiveValidationResult::STATUS_BLOCKED,
            message: $message,
            bandId: $bandId,
            deviceKey: $deviceKey,
            scene: $scene,
            mode: $mode,
            context: $context,
            occurredAt: now(),
        );
    }
}
