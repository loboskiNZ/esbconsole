<?php

namespace App\Services\X32;

/**
 * Read-only capture of PH042.03 routing domains from OSC query results.
 *
 * Domains: input banks, card routing, out 1–16.
 */
class X32RoutingLearnCapture
{
    public function __construct(
        private readonly X32RoutingEnumDecoder $enumDecoder = new X32RoutingEnumDecoder,
    ) {}

    /**
     * @param  callable(string): int  $queryInt
     * @param  (callable(string, int): void)|null  $onQuery
     * @return array<string, mixed>
     */
    public function capture(string $source, callable $queryInt, ?callable $onQuery = null): array
    {
        $rawOsc = [
            'routswitch' => $this->readPath(X32RoutingOscAddressMap::ROUTSWITCH, $queryInt, $onQuery),
            'input_banks' => $this->readPaths(X32RoutingOscAddressMap::INPUT_BANK_PATHS, $queryInt, $onQuery),
            'card' => $this->readPaths(X32RoutingOscAddressMap::CARD_ROUTING_PATHS, $queryInt, $onQuery),
            'out_1_16' => $this->readPaths(X32RoutingOscAddressMap::OUT_BANK_PATHS, $queryInt, $onQuery),
            'main_output_patch' => $this->readPaths(X32RoutingOscAddressMap::outputMainSrcPaths(), $queryInt, $onQuery),
        ];

        $normalized = [
            'routswitch' => $this->normalizeRoutswitch($rawOsc['routswitch']),
            'input_banks' => $this->normalizeInputBanks($rawOsc['input_banks']),
            'card_inputs' => $this->normalizeCardInputs($rawOsc),
            'out_1_16' => $this->normalizeOutBanks($rawOsc['out_1_16']),
            'main_lr' => $this->normalizeMainLr($rawOsc['main_output_patch']),
        ];

        $warnings = $this->collectWarnings($rawOsc, $normalized);

        return [
            'source' => $source,
            'learned_at' => now()->toIso8601String(),
            'raw_osc' => $rawOsc,
            'normalized' => $normalized,
            'warnings' => $warnings,
        ];
    }

    /**
     * Build routing payload from pre-seeded raw OSC values (fixture transport).
     *
     * @param  array<string, mixed>  $rawValues  path => int value
     * @return array<string, mixed>
     */
    public function captureFromRawValues(string $source, array $rawValues): array
    {
        return $this->capture(
            $source,
            fn (string $path): int => (int) ($rawValues[$path] ?? 0),
        );
    }

    /**
     * @param  list<string>  $paths
     * @param  callable(string): int  $queryInt
     * @param  (callable(string, int): void)|null  $onQuery
     * @return list<array<string, mixed>>
     */
    private function readPaths(array $paths, callable $queryInt, ?callable $onQuery): array
    {
        $entries = [];

        foreach ($paths as $path) {
            $entries[] = $this->readPath($path, $queryInt, $onQuery);
        }

        return $entries;
    }

    /**
     * @param  callable(string): int  $queryInt
     * @param  (callable(string, int): void)|null  $onQuery
     * @return array<string, mixed>
     */
    private function readPath(string $path, callable $queryInt, ?callable $onQuery): array
    {
        $value = $queryInt($path);
        $onQuery?->__invoke($path, $value);

        return [
            'path' => $path,
            'value' => $value,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeRoutswitch(array $raw): array
    {
        $index = (int) ($raw['value'] ?? 0);

        return [
            'osc_path' => (string) ($raw['path'] ?? X32RoutingOscAddressMap::ROUTSWITCH),
            'raw_index' => $index,
            'mode' => $this->enumDecoder->routswitchMode($index),
            'label' => $index === 0 ? 'REC' : ($index === 1 ? 'PLAY' : sprintf('UNKNOWN(%d)', $index)),
            'learned' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rawEntries
     * @return list<array<string, mixed>>
     */
    private function normalizeInputBanks(array $rawEntries): array
    {
        $normalized = [];

        foreach ($rawEntries as $entry) {
            $path = (string) ($entry['path'] ?? '');
            $index = (int) ($entry['value'] ?? 0);
            $label = $this->enumDecoder->inputBankLabel($index);
            $classification = $this->enumDecoder->classifySourceLabel($label);
            $meta = X32RoutingOscAddressMap::inputBankMeta($path);

            $normalized[] = [
                'bank' => $meta['bank'],
                'console_channel_range' => $meta['channels'],
                'console_channels' => range($meta['start'], $meta['end']),
                'osc_path' => $path,
                'raw_index' => $index,
                'raw_label' => $label,
                'source_type' => $classification['category'],
                'source_family' => $classification['family'],
                'source_range' => $label,
                'learned' => true,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $rawOsc
     * @return list<array<string, mixed>>
     */
    private function normalizeCardInputs(array $rawOsc): array
    {
        $entries = [];

        foreach ($rawOsc['input_banks'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $path = (string) ($entry['path'] ?? '');
            $index = (int) ($entry['value'] ?? 0);
            $label = $this->enumDecoder->inputBankLabel($index);

            if (! str_starts_with($label, 'CARD')) {
                continue;
            }

            $meta = X32RoutingOscAddressMap::inputBankMeta($path);

            $entries[] = [
                'context' => 'input_bank',
                'direction' => 'input',
                'osc_path' => $path,
                'raw_index' => $index,
                'raw_label' => $label,
                'card_range' => $label,
                'desk_channel_range' => $meta['channels'],
                'desk_channels' => range($meta['start'], $meta['end']),
                'learned' => true,
            ];
        }

        foreach ($rawOsc['card'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $path = (string) ($entry['path'] ?? '');
            $index = (int) ($entry['value'] ?? 0);
            $label = $this->enumDecoder->eightWideBlockLabel($index);
            $classification = $this->enumDecoder->classifySourceLabel($label);
            $meta = X32RoutingOscAddressMap::cardBlockMeta($path);

            $entries[] = [
                'context' => 'card_routing_table',
                'direction' => 'output',
                'osc_path' => $path,
                'raw_index' => $index,
                'raw_label' => $label,
                'card_block' => $meta['block'],
                'card_range' => $meta['card_range'],
                'source_type' => $classification['category'],
                'source_range' => $label,
                'learned' => true,
            ];
        }

        return $entries;
    }

    /**
     * @param  list<array<string, mixed>>  $rawEntries
     * @return list<array<string, mixed>>
     */
    private function normalizeOutBanks(array $rawEntries): array
    {
        $normalized = [];

        foreach ($rawEntries as $entry) {
            $path = (string) ($entry['path'] ?? '');
            $index = (int) ($entry['value'] ?? 0);
            $label = $this->enumDecoder->outBankLabelForPath($index, $path);
            $classification = $this->enumDecoder->classifySourceLabel($label);
            $meta = X32RoutingOscAddressMap::outBankMeta($path);

            $normalized[] = [
                'block' => $meta['block'],
                'output_range' => $meta['outputs'],
                'outputs' => range($meta['start'], $meta['end']),
                'osc_path' => $path,
                'raw_index' => $index,
                'raw_label' => $label,
                'source_type' => $classification['category'],
                'source_range' => $label,
                'learned' => true,
            ];
        }

        return $normalized;
    }

    /**
     * Derive Main L/R only from learned /outputs/main/NN/src assignments.
     *
     * Never assumes Out 15–16, XLR 1/2, or FOH mapping.
     *
     * @param  list<array<string, mixed>>  $rawEntries
     * @return array<string, mixed>
     */
    private function normalizeMainLr(array $rawEntries): array
    {
        $left = null;
        $right = null;

        foreach ($rawEntries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $path = (string) ($entry['path'] ?? '');
            $index = (int) ($entry['value'] ?? 0);
            $label = $this->enumDecoder->outputMainSrcLabel($index);
            $outputNumber = X32RoutingOscAddressMap::outputMainNumber($path);

            if ($outputNumber < 1) {
                continue;
            }

            $assignment = [
                'output_number' => $outputNumber,
                'osc_path' => $path,
                'raw_index' => $index,
                'raw_label' => $label,
                'learned' => true,
            ];

            if ($this->enumDecoder->isMainLeftLabel($label) && $left === null) {
                $left = $assignment;
            }

            if ($this->enumDecoder->isMainRightLabel($label) && $right === null) {
                $right = $assignment;
            }
        }

        if ($left === null && $right === null) {
            return [
                'state' => 'not_learned',
                'left' => null,
                'right' => null,
                'learned' => false,
            ];
        }

        return [
            'state' => ($left !== null && $right !== null) ? 'learned' : 'partial',
            'left' => $left,
            'right' => $right,
            'learned' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $rawOsc
     * @param  array<string, mixed>  $normalized
     * @return list<string>
     */
    private function collectWarnings(array $rawOsc, array $normalized): array
    {
        $warnings = [];

        foreach ($normalized['input_banks'] ?? [] as $bank) {
            if (($bank['source_type'] ?? '') === 'unknown') {
                $warnings[] = sprintf(
                    'Input bank %s has unknown routing index %d.',
                    $bank['bank'] ?? '?',
                    $bank['raw_index'] ?? -1,
                );
            }
        }

        if (($rawOsc['routswitch']['value'] ?? 0) === 1) {
            $warnings[] = 'Console routswitch is PLAY — IN bank values reflect playback path; PLAY bank paths were not read in PH042.03.01.';
        }

        return $warnings;
    }
}
