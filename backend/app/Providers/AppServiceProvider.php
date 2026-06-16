<?php

namespace App\Providers;

use App\Contracts\X32\X32ConsoleSnapshotReaderInterface;
use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Services\X32\FakeX32ConsoleSnapshotReader;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\OscUdpX32ConsoleSnapshotReader;
use App\Services\X32\OscUdpX32OscConsoleClient;
use App\Services\X32\RoutingX32ConsoleSnapshotReader;
use App\Services\X32\X32RoutingLearnCapture;
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
                timeoutSeconds: 0.2,
            );
        });

        $this->app->singleton(OscUdpX32ConsoleSnapshotReader::class, function ($app) {
            return new OscUdpX32ConsoleSnapshotReader(
                oscClient: $app->make(X32OscConsoleClientInterface::class),
                codec: new X32OscMessageCodec,
                sceneRecallBuilder: new X32OscSceneRecallPacketBuilder,
                sceneParameterResolver: new X32SceneParameterResolver,
                routingLearnCapture: new X32RoutingLearnCapture,
            );
        });

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
