<?php

namespace App\Providers;

use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Models\Library\SongAsset;
use App\Models\Library\SongInstrumentPart;
use App\Support\StudioLibraryAvailability;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->repairMergedPortalEnv();

        require_once dirname(base_path()).'/database/ccmm_migration_paths.php';

        foreach (ccmm_migration_paths() as $path) {
            $this->loadMigrationsFrom($path);
        }

        Route::bind('song', function (string $value): Song {
            abort_unless(app(StudioLibraryAvailability::class)->isAvailable(), 404);

            return Song::query()->findOrFail($value);
        });

        Route::bind('chart', function (string $value): Chart {
            abort_unless(app(StudioLibraryAvailability::class)->isAvailable(), 404);

            return Chart::query()->findOrFail($value);
        });

        Route::bind('songInstrumentPart', function (string $value): SongInstrumentPart {
            abort_unless(app(StudioLibraryAvailability::class)->isAvailable(), 404);

            return SongInstrumentPart::query()->findOrFail($value);
        });

        Route::bind('songAsset', function (string $value): SongAsset {
            abort_unless(app(StudioLibraryAvailability::class)->isAvailable(), 404);

            return SongAsset::query()->findOrFail($value);
        });
    }

    /**
     * Recover from a corrupted Forge .env line such as:
     * QUEUE_CONNECTION=databasePORTAL_LIBRARY_STORAGE_ROOT=/path/to/library
     */
    private function repairMergedPortalEnv(): void
    {
        $queue = (string) env('QUEUE_CONNECTION', '');

        if (! str_contains($queue, 'PORTAL_LIBRARY_STORAGE_ROOT=')) {
            return;
        }

        [$queueConnection, $libraryRoot] = explode('PORTAL_LIBRARY_STORAGE_ROOT=', $queue, 2);
        $libraryRoot = rtrim(trim($libraryRoot), '/');

        if ($libraryRoot === '') {
            return;
        }

        Config::set('queue.default', $queueConnection);
        Config::set('portal.library_storage_root', $libraryRoot);
        Config::set('filesystems.disks.library.root', $libraryRoot);
    }
}
