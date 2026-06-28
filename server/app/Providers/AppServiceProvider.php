<?php

namespace App\Providers;

use App\Contracts\DocxToPdfConverterInterface;
use App\Mail\Transport\GraphTransport;
use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Models\Library\SongAsset;
use App\Models\Library\SongInstrumentPart;
use App\Services\LibreOfficeDocxToPdfConverter;
use App\Services\MicrosoftGraph\GraphAccessTokenProvider;
use App\Support\StudioLibraryAvailability;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocxToPdfConverterInterface::class, LibreOfficeDocxToPdfConverter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->repairMergedPortalEnv();

        Mail::extend('graph', function (array $config): GraphTransport {
            return new GraphTransport(
                tokens: app(GraphAccessTokenProvider::class),
                sendAsMailbox: (string) ($config['send_as'] ?? config('services.microsoft.send_as', '')),
            );
        });

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

        ResetPassword::toMailUsing(function ($notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Reset your Ed and the Shadow Boys portal password')
                ->line('You are receiving this email because we received a password reset request for your Studio account.')
                ->action('Reset Password', $url)
                ->line('This password reset link will expire in '.config('auth.passwords.users.expire').' minutes.')
                ->line('If you did not request a password reset, no further action is required.');
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
