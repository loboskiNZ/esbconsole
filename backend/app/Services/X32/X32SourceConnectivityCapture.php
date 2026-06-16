<?php

namespace App\Services\X32;

/**
 * Read-only capture of X32 source link status from /-stat/* OSC paths.
 *
 * Routing input-bank assignment must never be used as a proxy for connectivity.
 */
class X32SourceConnectivityCapture
{
    private const AES50_A_AUDIO_ERR = 1;

    private const AES50_B_AUDIO_ERR = 2;

    /**
     * @param  callable(string): string  $queryString
     * @param  callable(string): int  $queryInt
     * @return array<string, mixed>
     */
    public function capture(string $source, callable $queryString, callable $queryInt): array
    {
        $rawOsc = [
            'aes50_a' => [
                'path' => X32SourceConnectivityOscAddressMap::AES50_A,
                'value' => $queryString(X32SourceConnectivityOscAddressMap::AES50_A),
            ],
            'aes50_b' => [
                'path' => X32SourceConnectivityOscAddressMap::AES50_B,
                'value' => $queryString(X32SourceConnectivityOscAddressMap::AES50_B),
            ],
            'aes50_state' => [
                'path' => X32SourceConnectivityOscAddressMap::AES50_STATE,
                'value' => $this->readAes50State($queryString, $queryInt),
            ],
            'xcard_type' => [
                'path' => X32SourceConnectivityOscAddressMap::XCARD_TYPE,
                'value' => $queryInt(X32SourceConnectivityOscAddressMap::XCARD_TYPE),
            ],
        ];

        return [
            'source' => $source,
            'learned_at' => now()->toIso8601String(),
            'raw_osc' => $rawOsc,
            'normalized' => $this->normalize($rawOsc),
        ];
    }

    /**
     * @param  array<string, int|string>  $rawValues  keyed by OSC path
     * @return array<string, mixed>
     */
    public function captureFromRawValues(string $source, array $rawValues): array
    {
        $rawOsc = [
            'aes50_a' => [
                'path' => X32SourceConnectivityOscAddressMap::AES50_A,
                'value' => (string) ($rawValues[X32SourceConnectivityOscAddressMap::AES50_A] ?? ''),
            ],
            'aes50_b' => [
                'path' => X32SourceConnectivityOscAddressMap::AES50_B,
                'value' => (string) ($rawValues[X32SourceConnectivityOscAddressMap::AES50_B] ?? ''),
            ],
            'aes50_state' => [
                'path' => X32SourceConnectivityOscAddressMap::AES50_STATE,
                'value' => (int) ($rawValues[X32SourceConnectivityOscAddressMap::AES50_STATE] ?? 0),
            ],
            'xcard_type' => [
                'path' => X32SourceConnectivityOscAddressMap::XCARD_TYPE,
                'value' => (int) ($rawValues[X32SourceConnectivityOscAddressMap::XCARD_TYPE] ?? 0),
            ],
        ];

        return [
            'source' => $source,
            'learned_at' => now()->toIso8601String(),
            'raw_osc' => $rawOsc,
            'normalized' => $this->normalize($rawOsc),
        ];
    }

    /**
     * @param  array<string, mixed>  $rawOsc
     * @return array<string, array{state: string, label: string, monitored: bool, detail?: string}>
     */
    public function normalize(array $rawOsc): array
    {
        $aes50State = (int) ($rawOsc['aes50_state']['value'] ?? 0);
        $xcardType = (int) ($rawOsc['xcard_type']['value'] ?? 0);

        return [
            'stagebox_a' => $this->normalizeAes50Port(
                (string) ($rawOsc['aes50_a']['value'] ?? ''),
                $aes50State,
                self::AES50_A_AUDIO_ERR,
                'AES50A',
            ),
            'stagebox_b' => $this->normalizeAes50Port(
                (string) ($rawOsc['aes50_b']['value'] ?? ''),
                $aes50State,
                self::AES50_B_AUDIO_ERR,
                'AES50B',
            ),
            'ableton' => $this->normalizeCardUsb($xcardType),
        ];
    }

    /**
     * @param  callable(string): string  $queryString
     * @param  callable(string): int  $queryInt
     */
    private function readAes50State(callable $queryString, callable $queryInt): int
    {
        try {
            return $queryInt(X32SourceConnectivityOscAddressMap::AES50_STATE);
        } catch (\Throwable) {
            $raw = trim(str_replace("\0", '', $queryString(X32SourceConnectivityOscAddressMap::AES50_STATE)));

            return is_numeric($raw) ? (int) $raw : 0;
        }
    }

    /**
     * @return array{state: string, label: string, monitored: bool, detail?: string}
     */
    private function normalizeAes50Port(string $chain, int $aes50State, int $audioErrorBit, string $label): array
    {
        $chainPresent = $this->isAes50ChainPresent($chain);
        $hasAudioError = ($aes50State & $audioErrorBit) !== 0;

        if (! $chainPresent) {
            return [
                'state' => 'offline',
                'label' => 'Offline',
                'monitored' => true,
                'detail' => sprintf('%s chain not detected', $label),
            ];
        }

        if ($hasAudioError) {
            return [
                'state' => 'offline',
                'label' => 'Offline',
                'monitored' => true,
                'detail' => sprintf('%s audio error reported', $label),
            ];
        }

        return [
            'state' => 'online',
            'label' => 'Online',
            'monitored' => true,
            'detail' => trim(str_replace("\0", '', $chain)),
        ];
    }

    /**
     * @return array{state: string, label: string, monitored: bool, detail?: string}
     */
    private function normalizeCardUsb(int $xcardType): array
    {
        if ($xcardType <= 0) {
            return [
                'state' => 'offline',
                'label' => 'Offline',
                'monitored' => true,
                'detail' => 'No expansion card reported',
            ];
        }

        return [
            'state' => 'online',
            'label' => 'Online',
            'monitored' => true,
            'detail' => $this->xcardTypeLabel($xcardType),
        ];
    }

    private function isAes50ChainPresent(string $chain): bool
    {
        $chain = trim(str_replace("\0", '', $chain));

        if ($chain === '') {
            return false;
        }

        return (bool) preg_match('/[A-Za-z]/', $chain);
    }

    private function xcardTypeLabel(int $type): string
    {
        return match ($type) {
            1 => 'X-UF',
            2 => 'X-USB',
            3 => 'X-DANTE',
            4 => 'X-ADAT',
            5 => 'X-MADI',
            6 => 'DN32-USB',
            7 => 'DN32-DANTE',
            8 => 'DN32-ADAT',
            9 => 'DN32-MADI',
            10 => 'X-Live',
            11 => 'WAVE X-WSG',
            default => sprintf('Card type %d', $type),
        };
    }
}
