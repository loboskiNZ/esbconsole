<?php

namespace App\Providers;

use App\Contracts\X32\X32ConsoleSnapshotReaderInterface;
use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Services\Effects\DeployEffectPackageItemService;
use App\Services\X32\FakeX32ConsoleSnapshotReader;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\OscUdpX32ConsoleSnapshotReader;
use App\Services\X32\OscUdpX32OscConsoleClient;
use App\Services\X32\RoutingX32ConsoleSnapshotReader;
use App\Services\X32\X32BusEqLearnCapture;
use App\Services\X32\X32ConfigurationIdentityCapture;
use App\Services\X32\X32MonitorSendMatrixLearnCapture;
use App\Services\X32\X32RoutingLearnCapture;
use App\Services\X32\X32SourceConnectivityCapture;
use App\Services\X32\X32SourceConnectivityService;
use App\Services\X32\X32OscMessageCodec;
use App\Services\X32\X32OscSceneRecallPacketBuilder;
use App\Services\X32\X32SceneParameterResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FakeX32OscConsoleClient::class);

        $this->app->singleton(X32OscConsoleClientInterface::class, function ($app) {
            if ($app->runningUnitTests()) {
                return $app->make(FakeX32OscConsoleClient::class);
            }

            return new OscUdpX32OscConsoleClient(
                new X32OscMessageCodec,
                timeoutSeconds: (float) config('services.x32.osc_timeout_seconds', 2.0),
            );
        });

        $this->app->when(DeployEffectPackageItemService::class)
            ->needs(X32OscConsoleClientInterface::class)
            ->give(function ($app) {
                if ($app->runningUnitTests()) {
                    return $app->make(FakeX32OscConsoleClient::class);
                }

                return new OscUdpX32OscConsoleClient(
                    new X32OscMessageCodec,
                    timeoutSeconds: (float) config('services.x32.fx_deploy_osc_timeout_seconds', 3.0),
                );
            });

        $this->app->singleton(OscUdpX32ConsoleSnapshotReader::class, function ($app) {
            return new OscUdpX32ConsoleSnapshotReader(
                oscClient: $app->make(X32OscConsoleClientInterface::class),
                codec: new X32OscMessageCodec,
                sceneRecallBuilder: new X32OscSceneRecallPacketBuilder,
                sceneParameterResolver: new X32SceneParameterResolver,
                routingLearnCapture: new X32RoutingLearnCapture,
                sourceConnectivityCapture: new X32SourceConnectivityCapture,
                configurationIdentityCapture: new X32ConfigurationIdentityCapture,
                busEqLearnCapture: new X32BusEqLearnCapture,
                monitorSendMatrixLearnCapture: new X32MonitorSendMatrixLearnCapture,
            );
        });

        $this->app->singleton(X32SourceConnectivityCapture::class);
        $this->app->singleton(X32SourceConnectivityService::class);
        $this->app->singleton(X32ConsoleSnapshotReaderInterface::class, RoutingX32ConsoleSnapshotReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
