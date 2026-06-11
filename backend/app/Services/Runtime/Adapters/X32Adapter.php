<?php

namespace App\Services\Runtime\Adapters;

use App\Contracts\Runtime\RuntimeAdapterInterface;
use App\Contracts\X32\X32TransportInterface;
use App\Models\RuntimeDispatchItem;
use App\Services\Runtime\AdapterExecutionRequest;
use App\Services\Runtime\AdapterExecutionResult;
use App\Services\X32\X32DispatchContextResolver;
use App\Services\X32\X32RuntimeModeResolver;
use App\Services\X32\X32SceneParameterResolver;
use App\Services\X32\X32SceneRecallCommand;

class X32Adapter implements RuntimeAdapterInterface
{
    public function __construct(
        private readonly X32DispatchContextResolver $contextResolver,
        private readonly X32SceneParameterResolver $sceneParameterResolver,
        private readonly X32TransportInterface $transport,
        private readonly X32RuntimeModeResolver $runtimeModeResolver,
        private readonly bool $dryRun = true,
    ) {}

    public function adapterKey(): string
    {
        return 'x32';
    }

    public function supports(RuntimeDispatchItem $item): bool
    {
        return $item->action_type_code === 'X32_SCENE';
    }

    public function execute(AdapterExecutionRequest $request): AdapterExecutionResult
    {
        $scene = $this->sceneParameterResolver->resolve($request->payload);

        if ($scene === null) {
            return AdapterExecutionResult::failed(
                adapterKey: $this->adapterKey(),
                message: 'X32 scene action requires a valid scene or scene_number parameter.',
                context: [
                    'adapter' => 'x32',
                    'mode' => $this->dryRun ? 'dry_run' : 'live',
                ],
            );
        }

        $context = $this->contextResolver->resolve($request->runtimeDispatchItemId);

        if ($context === null) {
            return AdapterExecutionResult::failed(
                adapterKey: $this->adapterKey(),
                message: 'No enabled X32 integration device with a valid connection profile is available.',
                context: [
                    'adapter' => 'x32',
                    'mode' => $this->dryRun ? 'dry_run' : 'live',
                    'scene' => $scene,
                ],
            );
        }

        $runtimeMode = $this->runtimeModeResolver->resolve($context->device->configuration);

        $transportResult = $this->transport->recallScene(new X32SceneRecallCommand(
            scene: $scene,
            deviceKey: $context->device->device_key,
            profileName: $context->profile->profile_name,
            protocol: $context->profile->protocol,
            host: $context->profile->host,
            port: $context->profile->port,
            dryRun: ! $this->runtimeModeResolver->isLive($runtimeMode),
            runtimeMode: $runtimeMode,
        ));

        if (! $transportResult->success) {
            return AdapterExecutionResult::failed(
                adapterKey: $this->adapterKey(),
                message: $transportResult->message ?? 'X32 scene recall failed.',
                context: $transportResult->context,
            );
        }

        return AdapterExecutionResult::acknowledged(
            adapterKey: $this->adapterKey(),
            message: $transportResult->message,
            context: $transportResult->context,
        );
    }
}
