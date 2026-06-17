<?php

namespace App\Services\Console;

/**
 * Builds the PH043.03B2C bus master EQ card from learned configuration data.
 */
class X32MonitorBusMasterEqCardBuilder
{
    private const BAND_COUNT = 6;

    private const GRAPH_FREQ_MIN = 20.0;

    private const GRAPH_FREQ_MAX = 20000.0;

    private const GRAPH_GAIN_MIN = -15.0;

    private const GRAPH_GAIN_MAX = 15.0;

    /** @var list<string> */
    private const MODE_OPTIONS = ['HCUT', 'HSHV', 'VEQ', 'PEQ', 'LSHV', 'LCUT'];

    /** @var list<string> */
    private const PLACEHOLDER_MODES = ['HCUT', 'HSHV', 'VEQ', 'PEQ', 'LSHV', 'LCUT'];

    /** @var list<string> */
    private const BAND_NAMES = ['Low', 'low2', 'low mid', 'high mid', 'high2', 'high'];

    /** @var list<float> */
    private const DEFAULT_FREQUENCIES_HZ = [79.6, 158.9, 498.6, 1970.0, 5020.0, 10020.0];

    /** @var list<string> */
    private const DEFAULT_FREQUENCY_INPUTS = ['79.6', '158.9', '498.6', '1K97', '5K02', '10K02'];

    private const DEFAULT_GAIN_DB = 0.0;

    private const DEFAULT_Q = 2.0;

    /**
     * @param  array<string, mixed>  $bus
     * @return array<string, mixed>
     */
    public function build(string $busName, array $bus): array
    {
        $eqBlock = is_array($bus['eq'] ?? null) ? $bus['eq'] : null;
        $learned = $this->eqBlockIsLearned($eqBlock);

        $enabled = $learned
            ? (bool) ($this->fieldValue($eqBlock['on'] ?? null) ?? false)
            : false;

        $bands = $this->buildBandSections($eqBlock, $learned);
        $graph = $this->buildGraph($bands);

        return [
            'title' => sprintf('%s — EQ', $busName),
            'scope_hint' => sprintf('Bus master EQ for %s', $busName),
            'layout_note' => 'X32-style bus master EQ layout · live control when console runtime is enabled',
            'learned' => $learned,
            'status_badge' => [
                'label' => $learned ? 'Learned from console' : 'EQ not learned from console yet',
                'state' => $learned ? 'learned' : 'placeholder',
            ],
            'placeholder_notice' => $learned
                ? null
                : 'Placeholder EQ display only. Values shown are not from the X32.',
            'enabled' => $enabled,
            'enabled_display' => $learned ? ($enabled ? 'ON' : 'OFF') : '—',
            'enabled_learned' => $learned && $this->fieldState($eqBlock['on'] ?? null) === 'learned',
            'mode_options' => self::MODE_OPTIONS,
            'bands' => $bands,
            'graph' => $graph,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $eqBlock
     */
    private function eqBlockIsLearned(?array $eqBlock): bool
    {
        if ($eqBlock === null) {
            return false;
        }

        if ($this->fieldState($eqBlock['on'] ?? null) === 'learned') {
            return true;
        }

        $lowCut = is_array($eqBlock['low_cut'] ?? null) ? $eqBlock['low_cut'] : null;

        if ($lowCut !== null && $this->fieldState($lowCut['frequency_hz'] ?? null) === 'learned') {
            return true;
        }

        foreach (is_array($eqBlock['bands'] ?? null) ? $eqBlock['bands'] : [] as $band) {
            if (! is_array($band)) {
                continue;
            }

            if ($this->fieldState($band['frequency_hz'] ?? null) === 'learned'
                || $this->fieldState($band['gain_db'] ?? null) === 'learned'
                || $this->fieldState($band['mode'] ?? null) === 'learned') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $eqBlock
     * @return list<array<string, mixed>>
     */
    private function buildBandSections(?array $eqBlock, bool $learned): array
    {
        $configuredBands = is_array($eqBlock['bands'] ?? null) ? $eqBlock['bands'] : [];
        $bands = [];

        for ($number = 1; $number <= self::BAND_COUNT; $number++) {
            $configured = $this->findConfiguredBand($configuredBands, $number);
            $bandLearned = $learned && $configured !== null && (
                $this->fieldState($configured['frequency_hz'] ?? null) === 'learned'
                || $this->fieldState($configured['gain_db'] ?? null) === 'learned'
                || $this->fieldState($configured['mode'] ?? null) === 'learned'
            );

            if (! $bandLearned && $learned && $number === 6) {
                $bandLearned = $this->legacyLowCutIsLearned($eqBlock);
                if ($bandLearned) {
                    $configured = $this->legacyLowCutAsBand($eqBlock);
                }
            }

            $placeholderMode = self::PLACEHOLDER_MODES[$number - 1] ?? 'PEQ';

            if ($bandLearned && $configured !== null) {
                $mode = $this->normalizeMode((string) ($this->fieldValue($configured['mode'] ?? null) ?? $placeholderMode));
                $bands[] = $this->buildBandRow($number, $mode, $configured, true);

                continue;
            }

            $bands[] = $this->buildBandRow($number, $placeholderMode, null, false);
        }

        return $bands;
    }

    /**
     * @param  array<string, mixed>|null  $configured
     * @return array<string, mixed>
     */
    private function buildBandRow(int $number, string $mode, ?array $configured, bool $bandLearned): array
    {
        $visibility = $this->modeFieldVisibility($mode);
        $defaults = $this->defaultForBand($number);

        $frequency = $bandLearned ? $this->fieldValue($configured['frequency_hz'] ?? null) : $defaults['frequency_hz'];
        $gain = $bandLearned ? $this->fieldValue($configured['gain_db'] ?? null) : self::DEFAULT_GAIN_DB;
        $q = $bandLearned ? $this->fieldValue($configured['q'] ?? null) : self::DEFAULT_Q;
        $qLearned = $bandLearned && $this->fieldState($configured['q'] ?? null) === 'learned';

        $frequencyHz = is_numeric($frequency) ? (float) $frequency : $defaults['frequency_hz'];
        $gainDb = is_numeric($gain) ? (float) $gain : self::DEFAULT_GAIN_DB;
        $qValue = is_numeric($q) ? (float) $q : self::DEFAULT_Q;

        return [
            'number' => $number,
            'label' => sprintf('Band %d', $number),
            'short_name' => self::BAND_NAMES[$number - 1] ?? sprintf('Band %d', $number),
            'mode' => $mode,
            'mode_options' => self::MODE_OPTIONS,
            'frequency_hz' => $frequencyHz,
            'frequency_input' => $bandLearned && is_numeric($frequency)
                ? $this->formatFrequencyInput($frequencyHz)
                : $defaults['frequency_input'],
            'frequency_display' => $this->formatFrequency($frequencyHz),
            'frequency_visible' => $visibility['frequency'],
            'gain_db' => $gainDb,
            'gain_input' => $this->formatGainInput($gainDb),
            'gain_display' => $this->formatGain($gainDb),
            'gain_visible' => $visibility['gain'],
            'q' => $qValue,
            'q_input' => $qLearned && is_numeric($q)
                ? number_format((float) $q, 2)
                : number_format(self::DEFAULT_Q, 0),
            'q_display' => ($visibility['q'] && $qLearned && is_numeric($q))
                ? number_format((float) $q, 2)
                : number_format(self::DEFAULT_Q, 0),
            'q_visible' => $visibility['q'],
            'learned' => $bandLearned,
            'is_placeholder' => ! $bandLearned,
            'color' => 'band-'.$number,
            'handle_gain_draggable' => $visibility['gain'],
        ];
    }

    /**
     * @return array{frequency_hz: float, frequency_input: string}
     */
    private function defaultForBand(int $number): array
    {
        $index = max(0, min(self::BAND_COUNT - 1, $number - 1));

        return [
            'frequency_hz' => self::DEFAULT_FREQUENCIES_HZ[$index],
            'frequency_input' => self::DEFAULT_FREQUENCY_INPUTS[$index],
        ];
    }

    /**
     * @return array{frequency: bool, gain: bool, q: bool}
     */
    private function modeFieldVisibility(string $mode): array
    {
        return match ($this->normalizeMode($mode)) {
            'LCUT', 'HCUT' => ['frequency' => true, 'gain' => false, 'q' => false],
            'LSHV', 'HSHV' => ['frequency' => true, 'gain' => true, 'q' => false],
            'VEQ', 'PEQ' => ['frequency' => true, 'gain' => true, 'q' => true],
            default => ['frequency' => true, 'gain' => true, 'q' => true],
        };
    }

    private function normalizeMode(string $mode): string
    {
        $normalized = mb_strtoupper(trim($mode));

        return match (true) {
            in_array($normalized, self::MODE_OPTIONS, true) => $normalized,
            str_contains($normalized, 'LOW CUT'), str_contains($normalized, 'LCUT') => 'LCUT',
            str_contains($normalized, 'HIGH CUT'), str_contains($normalized, 'HCUT') => 'HCUT',
            str_contains($normalized, 'LOW SHELF'), str_contains($normalized, 'LSHV') => 'LSHV',
            str_contains($normalized, 'HIGH SHELF'), str_contains($normalized, 'HSHV') => 'HSHV',
            str_contains($normalized, 'PARAM'), str_contains($normalized, 'PEQ') => 'PEQ',
            str_contains($normalized, 'VEQ') => 'VEQ',
            default => 'PEQ',
        };
    }

    /**
     * @param  array<string, mixed>|null  $eqBlock
     */
    private function legacyLowCutIsLearned(?array $eqBlock): bool
    {
        $lowCut = is_array($eqBlock['low_cut'] ?? null) ? $eqBlock['low_cut'] : null;

        return $lowCut !== null && $this->fieldState($lowCut['frequency_hz'] ?? null) === 'learned';
    }

    /**
     * @param  array<string, mixed>|null  $eqBlock
     * @return array<string, mixed>
     */
    private function legacyLowCutAsBand(?array $eqBlock): array
    {
        $lowCut = is_array($eqBlock['low_cut'] ?? null) ? $eqBlock['low_cut'] : [];

        return [
            'mode' => ['value' => 'LCUT', 'state' => 'learned'],
            'frequency_hz' => $lowCut['frequency_hz'] ?? ['value' => null, 'state' => 'not_learned'],
            'on' => $lowCut['on'] ?? ['value' => true, 'state' => 'learned'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $configuredBands
     * @return array<string, mixed>|null
     */
    private function findConfiguredBand(array $configuredBands, int $number): ?array
    {
        foreach ($configuredBands as $band) {
            if (! is_array($band)) {
                continue;
            }

            if ((int) ($band['number'] ?? 0) === $number) {
                return $band;
            }

            if ((string) ($band['key'] ?? '') === 'band_'.$number) {
                return $band;
            }
        }

        return $configuredBands[$number - 1] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $bands
     * @return array<string, mixed>
     */
    private function buildGraph(array $bands): array
    {
        $points = [];

        for ($step = 0; $step <= 80; $step++) {
            $ratio = $step / 80;
            $frequency = self::GRAPH_FREQ_MIN * ((self::GRAPH_FREQ_MAX / self::GRAPH_FREQ_MIN) ** $ratio);
            $gain = $this->approximateGainAtFrequency($frequency, $bands);
            $points[] = [
                'frequency_hz' => $frequency,
                'gain_db' => round($gain, 2),
                'x' => round($this->frequencyToGraphX($frequency), 2),
                'y' => round($this->gainToGraphY($gain), 2),
            ];
        }

        $bandNodes = [];

        foreach ($bands as $band) {
            if (! is_numeric($band['frequency_hz'] ?? null)) {
                continue;
            }

            $mode = $this->normalizeMode((string) ($band['mode'] ?? 'PEQ'));
            $gainDb = in_array($mode, ['LCUT', 'HCUT'], true)
                ? 0.0
                : (float) ($band['gain_db'] ?? 0.0);

            $bandNodes[] = [
                'number' => (int) $band['number'],
                'label' => (string) ($band['short_name'] ?? $band['number']),
                'color' => $band['color'],
                'mode' => $mode,
                'frequency_hz' => (float) $band['frequency_hz'],
                'gain_db' => $gainDb,
                'x' => round($this->frequencyToGraphX((float) $band['frequency_hz']), 2),
                'y' => round($this->gainToGraphY($gainDb), 2),
                'gain_draggable' => (bool) ($band['handle_gain_draggable'] ?? false),
            ];
        }

        return [
            'frequency_labels' => ['20Hz', '100Hz', '1kHz', '10kHz', '20kHz'],
            'gain_labels' => ['+15dB', '0dB', '-15dB'],
            'frequency_axis' => [20, 100, 1000, 10000, 20000],
            'gain_min' => self::GRAPH_GAIN_MIN,
            'gain_max' => self::GRAPH_GAIN_MAX,
            'freq_min' => self::GRAPH_FREQ_MIN,
            'freq_max' => self::GRAPH_FREQ_MAX,
            'path_d' => $this->pointsToSvgPath($points),
            'points' => $points,
            'band_nodes' => $bandNodes,
            'disclaimer' => 'Visual approximation only — not DSP accurate.',
            'uses_learned_points' => collect($bands)->contains(fn (array $band): bool => (bool) ($band['learned'] ?? false)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $bands
     */
    private function approximateGainAtFrequency(float $frequency, array $bands): float
    {
        $gain = 0.0;

        foreach ($bands as $band) {
            if (! is_numeric($band['frequency_hz'] ?? null)) {
                continue;
            }

            $mode = $this->normalizeMode((string) ($band['mode'] ?? 'PEQ'));
            $centerHz = (float) $band['frequency_hz'];

            if ($mode === 'LCUT' && $frequency <= $centerHz) {
                $ratio = max(0.0, $frequency / max(1.0, $centerHz));
                $gain -= (1.0 - $ratio) * 12.0;

                continue;
            }

            if ($mode === 'HCUT' && $frequency >= $centerHz) {
                $ratio = max(0.0, $centerHz / max(1.0, $frequency));
                $gain -= (1.0 - $ratio) * 12.0;

                continue;
            }

            $gainDb = (float) ($band['gain_db'] ?? 0.0);
            $q = is_numeric($band['q'] ?? null) ? max(0.1, (float) $band['q']) : self::DEFAULT_Q;

            if (in_array($mode, ['VEQ', 'PEQ'], true)) {
                $gain += $this->bellContribution($frequency, $centerHz, $gainDb, $q);
            } elseif (in_array($mode, ['LSHV', 'HSHV'], true)) {
                $gain += $this->bellContribution($frequency, $centerHz, $gainDb, 0.71);
            }
        }

        return max(self::GRAPH_GAIN_MIN, min(self::GRAPH_GAIN_MAX, $gain));
    }

    private function bellContribution(float $frequency, float $centerHz, float $gainDb, float $q): float
    {
        if ($centerHz <= 0.0 || abs($gainDb) < 0.01) {
            return 0.0;
        }

        $octaves = log($frequency / $centerHz, 2);
        $width = max(0.25, 1.0 / $q);

        return $gainDb * exp(-0.5 * ($octaves / $width) ** 2);
    }

    /**
     * @param  list<array{x: float, y: float}>  $points
     */
    private function pointsToSvgPath(array $points): string
    {
        if ($points === []) {
            return '';
        }

        $first = $points[0];
        $path = sprintf('M%.2f,%.2f', $first['x'], $first['y']);

        for ($index = 1; $index < count($points); $index++) {
            $point = $points[$index];
            $path .= sprintf(' L%.2f,%.2f', $point['x'], $point['y']);
        }

        return $path;
    }

    private function frequencyToGraphX(float $frequency): float
    {
        $ratio = log(max(self::GRAPH_FREQ_MIN, $frequency) / self::GRAPH_FREQ_MIN, self::GRAPH_FREQ_MAX / self::GRAPH_FREQ_MIN);

        return $ratio * 640;
    }

    private function gainToGraphY(float $gainDb): float
    {
        $clamped = max(self::GRAPH_GAIN_MIN, min(self::GRAPH_GAIN_MAX, $gainDb));
        $normalized = ($clamped - self::GRAPH_GAIN_MIN) / (self::GRAPH_GAIN_MAX - self::GRAPH_GAIN_MIN);

        return 150 - ($normalized * 120);
    }

    private function formatFrequency(float $frequencyHz): string
    {
        if ($frequencyHz >= 1000) {
            $khz = $frequencyHz / 1000;

            return rtrim(rtrim(number_format($khz, $khz >= 10 ? 0 : 1), '0'), '.').' kHz';
        }

        return number_format($frequencyHz, 0).' Hz';
    }

    private function formatGain(float $gainDb): string
    {
        $prefix = $gainDb > 0 ? '+' : '';

        return $prefix.number_format($gainDb, 1).' dB';
    }

    private function formatGainInput(float $gainDb): string
    {
        if (abs($gainDb) < 0.05) {
            return '0';
        }

        $prefix = $gainDb > 0 ? '+' : '';

        return $prefix.number_format($gainDb, 1);
    }

    private function formatFrequencyInput(float $frequencyHz): string
    {
        if ($frequencyHz >= 1000) {
            $khz = $frequencyHz / 1000;
            $whole = (int) floor($khz);
            $fraction = (int) round(($khz - $whole) * 100);

            return sprintf('%dK%02d', $whole, $fraction);
        }

        return rtrim(rtrim(number_format($frequencyHz, 1), '0'), '.');
    }

    private function fieldValue(mixed $field): mixed
    {
        if (! is_array($field) || ! array_key_exists('value', $field)) {
            return is_array($field) ? null : $field;
        }

        return $field['value'];
    }

    private function fieldState(mixed $field): string
    {
        if (is_array($field) && is_string($field['state'] ?? null)) {
            return (string) $field['state'];
        }

        return 'not_learned';
    }
}
