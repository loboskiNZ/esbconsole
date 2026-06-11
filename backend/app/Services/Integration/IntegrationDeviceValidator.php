<?php

namespace App\Services\Integration;

use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;

class IntegrationDeviceValidator
{
    /** @var list<string> */
    private const SUPPORTED_PROTOCOLS = [
        IntegrationConnectionProfile::PROTOCOL_OSC,
        IntegrationConnectionProfile::PROTOCOL_MIDI,
        IntegrationConnectionProfile::PROTOCOL_TCP,
        IntegrationConnectionProfile::PROTOCOL_UDP,
        IntegrationConnectionProfile::PROTOCOL_HTTP,
        IntegrationConnectionProfile::PROTOCOL_LOCAL,
        IntegrationConnectionProfile::PROTOCOL_CUSTOM,
    ];

    /** @var list<string> */
    private const NETWORK_PROTOCOLS = [
        IntegrationConnectionProfile::PROTOCOL_OSC,
        IntegrationConnectionProfile::PROTOCOL_TCP,
        IntegrationConnectionProfile::PROTOCOL_UDP,
        IntegrationConnectionProfile::PROTOCOL_HTTP,
    ];

    /** @var list<string> */
    private const X32_PROTOCOLS = [
        IntegrationConnectionProfile::PROTOCOL_OSC,
        IntegrationConnectionProfile::PROTOCOL_TCP,
    ];

    public function validate(IntegrationDevice $device): IntegrationValidationResult
    {
        $device->loadMissing('integrationConnectionProfiles');

        if (! $device->enabled) {
            $result = IntegrationValidationResult::skipped(
                message: 'Integration device is disabled.',
                context: ['device_key' => $device->device_key],
            );

            $this->persistDeviceValidation($device, IntegrationDevice::CONNECTION_STATUS_DISABLED, $result);

            return $result;
        }

        $enabledProfiles = $device->integrationConnectionProfiles
            ->where('enabled', true)
            ->values();

        if ($device->device_type === IntegrationDevice::TYPE_X32) {
            $x32Profile = $enabledProfiles->first(
                fn (IntegrationConnectionProfile $profile) => in_array($profile->protocol, self::X32_PROTOCOLS, true)
                    && $this->hasNetworkEndpoint($profile),
            );

            if ($x32Profile === null) {
                $result = IntegrationValidationResult::invalid(
                    message: 'X32 device requires an enabled OSC or TCP profile with host and port.',
                    context: ['device_type' => $device->device_type],
                );

                $this->persistDeviceValidation($device, IntegrationDevice::CONNECTION_STATUS_INVALID, $result);

                return $result;
            }
        }

        if ($enabledProfiles->isEmpty()) {
            $result = IntegrationValidationResult::invalid(
                message: 'Integration device requires at least one enabled connection profile.',
            );

            $this->persistDeviceValidation($device, IntegrationDevice::CONNECTION_STATUS_INVALID, $result);

            return $result;
        }

        foreach ($enabledProfiles as $profile) {
            $profileResult = $this->validateProfile($profile);

            $profile->update([
                'last_validated_at' => $profileResult->occurredAt,
                'last_validation_message' => $profileResult->message,
            ]);

            if (! $profileResult->success && $profileResult->status !== IntegrationValidationResult::STATUS_SKIPPED) {
                $this->persistDeviceValidation($device, IntegrationDevice::CONNECTION_STATUS_INVALID, $profileResult);

                return $profileResult;
            }
        }

        $result = IntegrationValidationResult::valid(
            message: 'Integration device configuration is valid.',
            context: [
                'device_key' => $device->device_key,
                'profile_count' => $enabledProfiles->count(),
            ],
        );

        $this->persistDeviceValidation($device, IntegrationDevice::CONNECTION_STATUS_VALID, $result);

        return $result;
    }

    public function validateProfile(IntegrationConnectionProfile $profile): IntegrationValidationResult
    {
        if (! $profile->enabled) {
            return IntegrationValidationResult::skipped(
                message: 'Connection profile is disabled.',
                context: ['profile_name' => $profile->profile_name],
            );
        }

        if (! in_array($profile->protocol, self::SUPPORTED_PROTOCOLS, true)) {
            return IntegrationValidationResult::unsupported(
                message: "Protocol [{$profile->protocol}] is not supported.",
                context: ['protocol' => $profile->protocol],
            );
        }

        if ($profile->protocol === IntegrationConnectionProfile::PROTOCOL_CUSTOM
            && ($profile->options === null || $profile->options === [])) {
            return IntegrationValidationResult::invalid(
                message: 'Custom protocol profile requires options configuration.',
                context: ['profile_name' => $profile->profile_name],
            );
        }

        if (in_array($profile->protocol, self::NETWORK_PROTOCOLS, true)
            && ! $this->hasNetworkEndpoint($profile)) {
            return IntegrationValidationResult::invalid(
                message: 'Network profile requires host and port.',
                context: [
                    'profile_name' => $profile->profile_name,
                    'protocol' => $profile->protocol,
                ],
            );
        }

        return IntegrationValidationResult::valid(
            message: 'Connection profile configuration is valid.',
            context: [
                'profile_name' => $profile->profile_name,
                'protocol' => $profile->protocol,
            ],
        );
    }

    private function hasNetworkEndpoint(IntegrationConnectionProfile $profile): bool
    {
        return $profile->host !== null
            && $profile->host !== ''
            && $profile->port !== null
            && $profile->port > 0;
    }

    private function persistDeviceValidation(
        IntegrationDevice $device,
        string $connectionStatus,
        IntegrationValidationResult $result,
    ): void {
        $device->update([
            'connection_status' => $connectionStatus,
            'last_validated_at' => $result->occurredAt,
        ]);
    }
}
