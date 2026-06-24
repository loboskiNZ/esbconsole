<?php

namespace App\Providers;

use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Support\StudioLibraryAvailability;
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
    }
}
