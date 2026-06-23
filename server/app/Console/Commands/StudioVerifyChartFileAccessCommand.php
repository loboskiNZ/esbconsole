<?php

namespace App\Console\Commands;

use App\Models\Library\Chart;
use App\Models\User;
use App\Services\StudioChartAccessService;
use App\Support\StudioLibraryChartStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class StudioVerifyChartFileAccessCommand extends Command
{
    protected $signature = 'studio:verify-chart-file-access
                            {chart : Chart id to verify}
                            {user : Portal user id to authorize as}';

    protected $description = 'Verify chart PDF storage and authenticated HTTP access on production';

    public function handle(
        StudioChartAccessService $chartAccess,
        StudioLibraryChartStorage $chartStorage,
    ): int {
        $chart = Chart::query()->find($this->argument('chart'));
        $user = User::query()->find($this->argument('user'));

        if ($chart === null || $user === null || $user->person_id === null) {
            $this->error('Chart or user not found.');

            return self::FAILURE;
        }

        $reference = (string) $chart->storage_reference;
        $absolutePath = $chartStorage->absolutePath($reference);
        $diskExists = Storage::disk((string) config('portal.library_chart_disk', 'library'))
            ->exists($chartStorage->diskRelativePath($reference));

        $this->line('release='.base_path());
        $this->line('php_user='.(function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : 'unknown'));
        $this->line('library_root='.config('filesystems.disks.library.root'));
        $this->line('storage_reference='.$reference);
        $this->line('absolute_path='.$absolutePath);
        $this->line('file_exists='.(is_file($absolutePath) ? 'yes' : 'no'));
        $this->line('is_readable='.(is_readable($absolutePath) ? 'yes' : 'no'));
        $this->line('storage_disk_exists='.($diskExists ? 'yes' : 'no'));

        if (! $diskExists || ! is_readable($absolutePath)) {
            $this->error('Chart file is not readable by the PHP process.');

            return self::FAILURE;
        }

        $person = $user->person()->with('instruments')->firstOrFail();
        $authorized = $chartAccess->personCanAccessChart($person, $chart);
        $this->line('authorized='.($authorized ? 'yes' : 'no'));

        if (! $authorized) {
            $this->error('User is not authorized for this chart (expected 403, not 404).');

            return self::FAILURE;
        }

        $httpStatus = $this->probeAuthenticatedHttp($user, $chart);

        $this->line('http_status='.$httpStatus);

        if ($httpStatus !== 200) {
            $this->error('Authenticated HTTP probe did not return 200.');

            return self::FAILURE;
        }

        $this->info('Chart file access verified.');

        return self::SUCCESS;
    }

    private function probeAuthenticatedHttp(User $user, Chart $chart): int
    {
        $session = app('session.store');
        $session->start();
        Auth::login($user);
        $session->save();

        $sessionName = (string) config('session.cookie');
        $sessionId = $session->getId();
        $cookieValue = $this->encryptSessionCookie($sessionName, $sessionId);

        $url = route('studio.charts.file', $chart, absolute: true);

        $response = Http::withOptions([
            'allow_redirects' => false,
            'verify' => true,
        ])->withHeaders([
            'Cookie' => "{$sessionName}={$cookieValue}",
            'Accept' => 'application/pdf',
        ])->get($url);

        return $response->status();
    }

    private function encryptSessionCookie(string $name, string $sessionId): string
    {
        $encrypter = app('encrypter');
        $key = $encrypter->getKey();

        if (class_exists(\Illuminate\Cookie\CookieValuePrefix::class)) {
            return \Illuminate\Cookie\CookieValuePrefix::create($name, $key)
                .$encrypter->encrypt($sessionId, false);
        }

        return $encrypter->encrypt($sessionId, false);
    }
}
