<?php

namespace App\Services\Console;

use App\Models\ConsoleLearningSnapshot;
use App\Services\X32\X32OscAddressMap;

/**
 * Ensures workspace strip rows include OSC paths and desk metadata for display/control.
 */
class ShowConsoleStripEnricher
{
    /**
     * @param  array<int, array<string, mixed>>  $strips
     * @return array<int, array<string, mixed>>
     */
    public function enrich(
        array $strips,
        string $layer,
        ?ConsoleLearningSnapshot $sourceSnapshot = null,
    ): array {
        $referenceStrips = $this->referenceStrips($sourceSnapshot, $layer);
        $oscIndex = $this->buildOscIndex($sourceSnapshot?->raw_snapshot_json ?? []);

        return array_map(function (array $strip) use ($layer, $referenceStrips, $oscIndex) {
            $index = (int) ($strip['index'] ?? 0);

            if ($index < 1) {
                return $strip;
            }

            $reference = $this->findStripByIndex($referenceStrips, $index);

            if ($reference !== null) {
                $strip = $this->mergeMissingFields($strip, $reference, ['name', 'color', 'fader', 'mute', 'controls']);
            }

            $strip = $this->mergeOscMetadata($strip, $layer, $index, $oscIndex);

            if ($layer === 'channel') {
                $strip = $this->mergeChannelControls($strip, $index, $oscIndex);
            }

            foreach ($this->oscPathsFor($layer, $index) as $key => $path) {
                if (empty($strip[$key])) {
                    $strip[$key] = $path;
                }
            }

            return $strip;
        }, $strips);
    }

    public function metadataIncomplete(array $channels): bool
    {
        if ($channels === []) {
            return false;
        }

        $first = $channels[0];

        return ! array_key_exists('color', $first)
            || trim((string) ($first['name'] ?? '')) === ''
            || preg_match('/^CH \d{2} Scene \d+$/i', (string) ($first['name'] ?? '')) === 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $strips
     * @return array<int, array<string, mixed>>
     */
    private function referenceStrips(?ConsoleLearningSnapshot $snapshot, string $layer): array
    {
        if ($snapshot === null) {
            return [];
        }

        $summary = $snapshot->learned_summary_json ?? [];
        $key = match ($layer) {
            'channel' => 'channels',
            'bus' => 'buses',
            'dca' => 'dcas',
            'matrix' => 'matrices',
            default => null,
        };

        return $key === null ? [] : ($summary[$key] ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $strips
     */
    private function findStripByIndex(array $strips, int $index): ?array
    {
        foreach ($strips as $strip) {
            if ((int) ($strip['index'] ?? 0) === $index) {
                return $strip;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rawSnapshot
     * @return array<string, mixed>
     */
    private function buildOscIndex(array $rawSnapshot): array
    {
        $index = [];

        foreach ($rawSnapshot['osc_responses'] ?? [] as $response) {
            if (! is_array($response)) {
                continue;
            }

            $path = (string) ($response['path'] ?? '');

            if ($path === '') {
                continue;
            }

            $index[$path] = $response['value'] ?? null;
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $strip
     * @param  array<string, mixed>  $oscIndex
     * @return array<string, mixed>
     */
    private function mergeOscMetadata(array $strip, string $layer, int $index, array $oscIndex): array
    {
        $namePath = match ($layer) {
            'channel' => X32OscAddressMap::channelName($index),
            'bus' => X32OscAddressMap::busName($index),
            default => null,
        };

        $colorPath = match ($layer) {
            'channel' => X32OscAddressMap::channelColor($index),
            'bus' => X32OscAddressMap::busColor($index),
            default => null,
        };

        $faderPath = match ($layer) {
            'channel' => X32OscAddressMap::channelFader($index),
            'bus' => X32OscAddressMap::busFader($index),
            'dca' => X32OscAddressMap::dcaFader($index),
            'matrix' => X32OscAddressMap::matrixFader($index),
            default => null,
        };

        $onPath = match ($layer) {
            'channel' => X32OscAddressMap::channelOn($index),
            'bus' => X32OscAddressMap::busOn($index),
            'dca' => X32OscAddressMap::dcaOn($index),
            'matrix' => X32OscAddressMap::matrixOn($index),
            default => null,
        };

        if ($namePath !== null && array_key_exists($namePath, $oscIndex) && empty(trim((string) ($strip['name'] ?? '')))) {
            $strip['name'] = (string) $oscIndex[$namePath];
        }

        if ($colorPath !== null && ! array_key_exists('color', $strip) && array_key_exists($colorPath, $oscIndex)) {
            $strip['color'] = (int) $oscIndex[$colorPath];
        }

        if ($faderPath !== null && array_key_exists($faderPath, $oscIndex) && ! array_key_exists('fader', $strip)) {
            $strip['fader'] = round((float) $oscIndex[$faderPath], 4);
        }

        if ($onPath !== null && array_key_exists($onPath, $oscIndex) && ! array_key_exists('mute', $strip)) {
            $strip['mute'] = ! (bool) $oscIndex[$onPath];
        }

        return $strip;
    }

    /**
     * @param  array<string, mixed>  $strip
     * @param  array<string, mixed>  $oscIndex
     * @return array<string, mixed>
     */
    private function mergeChannelControls(array $strip, int $index, array $oscIndex): array
    {
        $controls = is_array($strip['controls'] ?? null) ? $strip['controls'] : [];

        $paths = [
            'gate_on' => X32OscAddressMap::channelGateOn($index),
            'compressor_on' => X32OscAddressMap::channelDynOn($index),
            'eq_on' => X32OscAddressMap::channelEqOn($index),
            'pan' => X32OscAddressMap::channelPan($index),
            'main_lr' => X32OscAddressMap::channelLr($index),
        ];

        foreach ($paths as $key => $path) {
            if (array_key_exists($key, $controls) || ! array_key_exists($path, $oscIndex)) {
                continue;
            }

            $value = $oscIndex[$path];
            $controls[$key] = $key === 'pan'
                ? round((float) $value, 4)
                : (bool) $value;
        }

        if ($controls !== []) {
            $strip['controls'] = $controls;
        }

        return $strip;
    }

    /**
     * @param  array<string, mixed>  $strip
     * @param  array<string, mixed>  $reference
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function mergeMissingFields(array $strip, array $reference, array $fields): array
    {
        foreach ($fields as $field) {
            if ($field === 'controls') {
                $referenceControls = is_array($reference['controls'] ?? null) ? $reference['controls'] : [];
                $stripControls = is_array($strip['controls'] ?? null) ? $strip['controls'] : [];
                $strip['controls'] = array_merge($referenceControls, $stripControls);

                continue;
            }

            if (! array_key_exists($field, $strip) && array_key_exists($field, $reference)) {
                $strip[$field] = $reference[$field];
            }
        }

        return $strip;
    }

    /**
     * @return array<string, string>
     */
    private function oscPathsFor(string $layer, int $index): array
    {
        return match ($layer) {
            'channel' => [
                'osc_fader' => X32OscAddressMap::channelFader($index),
                'osc_on' => X32OscAddressMap::channelOn($index),
            ],
            'bus' => [
                'osc_fader' => X32OscAddressMap::busFader($index),
                'osc_on' => X32OscAddressMap::busOn($index),
            ],
            'dca' => [
                'osc_fader' => X32OscAddressMap::dcaFader($index),
                'osc_on' => X32OscAddressMap::dcaOn($index),
            ],
            'matrix' => [
                'osc_fader' => X32OscAddressMap::matrixFader($index),
                'osc_on' => X32OscAddressMap::matrixOn($index),
            ],
            default => [],
        };
    }
}
