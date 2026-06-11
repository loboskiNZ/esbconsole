<?php

namespace App\Services\X32;

use App\Models\IntegrationDevice;
use App\Models\PerformanceDeviceAssignment;
use App\Services\Integration\IntegrationDeviceRegistry;

class X32DeviceSelector
{
    public function __construct(
        private readonly IntegrationDeviceRegistry $deviceRegistry,
    ) {}

    public function select(
        int $bandId,
        ?int $performanceId = null,
        ?string $deviceKey = null,
    ): ?X32DeviceSelectionResult {
        if ($performanceId !== null) {
            $assignmentResult = $this->selectFromPerformanceAssignment($performanceId, $bandId);

            if ($assignmentResult !== null) {
                return $assignmentResult;
            }
        }

        if ($deviceKey !== null) {
            $device = $this->deviceRegistry->resolve(
                $bandId,
                IntegrationDevice::TYPE_X32,
                $deviceKey,
            );

            if ($device !== null) {
                return new X32DeviceSelectionResult(
                    device: $device,
                    selectionSource: X32DeviceSelectionResult::SOURCE_EXPLICIT_DEVICE_KEY,
                );
            }

            return null;
        }

        $device = $this->deviceRegistry->resolve(
            $bandId,
            IntegrationDevice::TYPE_X32,
        );

        if ($device === null) {
            return null;
        }

        return new X32DeviceSelectionResult(
            device: $device,
            selectionSource: X32DeviceSelectionResult::SOURCE_BAND_FALLBACK,
        );
    }

    private function selectFromPerformanceAssignment(int $performanceId, int $bandId): ?X32DeviceSelectionResult
    {
        $assignment = PerformanceDeviceAssignment::query()
            ->where('performance_id', $performanceId)
            ->whereHas('integrationDevice', function ($query) use ($bandId) {
                $query->where('band_id', $bandId)
                    ->where('device_type', IntegrationDevice::TYPE_X32)
                    ->where('enabled', true);
            })
            ->with('integrationDevice')
            ->orderByRaw(
                "CASE role WHEN '".PerformanceDeviceAssignment::ROLE_FOH."' THEN 0 "
                ."WHEN '".PerformanceDeviceAssignment::ROLE_MONITORS."' THEN 1 "
                ."WHEN '".PerformanceDeviceAssignment::ROLE_BACKUP."' THEN 2 "
                .'ELSE 3 END',
            )
            ->orderBy('id')
            ->first();

        if ($assignment === null) {
            return null;
        }

        return new X32DeviceSelectionResult(
            device: $assignment->integrationDevice,
            selectionSource: X32DeviceSelectionResult::SOURCE_PERFORMANCE_ASSIGNMENT,
        );
    }
}
