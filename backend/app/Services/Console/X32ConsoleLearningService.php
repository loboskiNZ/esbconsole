<?php

namespace App\Services\Console;

use App\Contracts\X32\X32ConsoleSnapshotReaderInterface;
use App\DataTransferObjects\X32\X32ConsoleLearnCommand;
use App\Enums\ConsoleLearningStatus;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class X32ConsoleLearningService
{
    public function __construct(
        private readonly X32ConsoleSnapshotReaderInterface $snapshotReader,
    ) {}

    public function startLearning(
        Show $show,
        IntegrationDevice $device,
        string $requestedSceneNumber,
    ): ConsoleLearningSnapshot {
        $this->ensureX32Device($device);
        $this->ensureDeviceBelongsToShowBand($show, $device);

        $profile = $this->resolveOscProfile($device);

        $snapshot = ConsoleLearningSnapshot::create([
            'band_id' => $show->band_id,
            'show_id' => $show->id,
            'integration_device_id' => $device->id,
            'requested_scene_number' => $requestedSceneNumber,
            'learning_status' => ConsoleLearningStatus::Learning,
        ]);

        $result = $this->snapshotReader->learnScene(new X32ConsoleLearnCommand(
            device: $device,
            requestedSceneNumber: $requestedSceneNumber,
            host: $profile->host ?? '127.0.0.1',
            port: (int) ($profile->port ?? 10023),
        ));

        if (! $result->success) {
            $snapshot->update([
                'learning_status' => ConsoleLearningStatus::Failed,
                'errors_json' => $result->errors,
                'warnings_json' => $result->warnings,
                'learned_at' => now(),
            ]);

            return $snapshot->fresh(['show', 'integrationDevice']);
        }

        $snapshot->update([
            'learning_status' => ConsoleLearningStatus::Review,
            'learned_summary_json' => $result->summary,
            'raw_snapshot_json' => $result->rawSnapshot,
            'warnings_json' => $result->warnings,
            'errors_json' => $result->errors,
            'learned_at' => now(),
        ]);

        return $snapshot->fresh(['show', 'integrationDevice']);
    }

    private function ensureX32Device(IntegrationDevice $device): void
    {
        if ($device->device_type !== IntegrationDevice::TYPE_X32 || ! $device->enabled) {
            throw ValidationException::withMessages([
                'integration_device_id' => 'Select an enabled X32/M32 console device.',
            ]);
        }
    }

    private function ensureDeviceBelongsToShowBand(Show $show, IntegrationDevice $device): void
    {
        if ($device->band_id !== $show->band_id) {
            throw ValidationException::withMessages([
                'integration_device_id' => 'Console device must belong to the same band as the show.',
            ]);
        }
    }

    private function resolveOscProfile(IntegrationDevice $device): IntegrationConnectionProfile
    {
        $profile = $device->integrationConnectionProfiles()
            ->where('enabled', true)
            ->whereIn('protocol', [
                IntegrationConnectionProfile::PROTOCOL_OSC,
                IntegrationConnectionProfile::PROTOCOL_UDP,
            ])
            ->orderBy('id')
            ->first();

        if ($profile === null) {
            throw ValidationException::withMessages([
                'integration_device_id' => 'Selected console has no enabled OSC/UDP connection profile.',
            ]);
        }

        return $profile;
    }
}
