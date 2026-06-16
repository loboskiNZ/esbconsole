<?php

namespace App\Services\X32;

/**
 * Builds the PH043 configuration domain block from learned console summary data.
 *
 * Preserves flat summary keys for backward compatibility. Does not move routing
 * or connectivity data into configuration.
 */
class X32ConfigurationLearnAssembler
{
    /**
     * Attach structured configuration to a learned summary.
     *
     * Removes configuration_capture from the summary; the learning service persists
     * that raw evidence in raw_snapshot_json.configuration_capture.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function attach(array $summary): array
    {
        $capture = is_array($summary['configuration_capture'] ?? null)
            ? $summary['configuration_capture']
            : [];

        unset($summary['configuration_capture']);

        $summary['configuration'] = $this->build($summary, $capture);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $capture
     * @return array<string, mixed>
     */
    public function build(array $summary, array $capture = []): array
    {
        $transport = (string) ($summary['transport'] ?? 'unknown');
        $isLive = $transport === 'live_osc';
        $warnings = [];

        if (! $isLive) {
            $warnings[] = 'Configuration identity globals require live OSC transport.';
        }

        return [
            'learned_at' => now()->toIso8601String(),
            'source' => $transport,
            'identity' => $this->buildIdentity($summary, $capture, $isLive),
            'channels' => $this->buildChannels($summary, $capture, $isLive),
            'buses' => $this->buildBuses($summary, $capture, $isLive),
            'dcas' => $this->buildDcas($summary, $isLive),
            'matrices' => $this->buildMatrices($summary, $isLive),
            'fx' => $this->buildFx($summary, $isLive),
            'globals' => $this->buildGlobals($summary, $capture, $isLive),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $capture
     * @return array<string, mixed>
     */
    private function buildIdentity(array $summary, array $capture, bool $isLive): array
    {
        $identityCapture = is_array($capture['identity'] ?? null) ? $capture['identity'] : [];
        $sceneName = trim((string) ($summary['scene_name'] ?? ''));

        return [
            'console_name' => $this->learnedField(
                (string) ($summary['device_name'] ?? ''),
                true,
            ),
            'device_key' => $this->learnedField(
                (string) ($summary['device_key'] ?? ''),
                true,
            ),
            'model' => $this->learnedField(
                strtoupper((string) ($summary['console_type'] ?? 'x32')),
                true,
                reason: 'device_configuration',
            ),
            'firmware' => $identityCapture['firmware'] ?? $this->notLearned('info_query_not_implemented'),
            'scene_number' => $this->learnedField(
                (string) ($summary['scene_number'] ?? $summary['requested_scene_number'] ?? ''),
                ($summary['scene_number'] ?? null) !== null,
            ),
            'scene_name' => $sceneName !== ''
                ? $this->learnedField($sceneName, true)
                : $this->notLearned($isLive ? 'desk_scene_name_unavailable' : 'fixture_transport'),
            'sample_rate' => $identityCapture['sample_rate'] ?? $this->notLearned(
                $isLive ? 'not_queried' : 'fixture_transport',
            ),
            'clock_source' => $identityCapture['clock_source'] ?? $this->notLearned(
                $isLive ? 'not_queried' : 'fixture_transport',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $capture
     * @return list<array<string, mixed>>
     */
    private function buildChannels(array $summary, array $capture, bool $isLive): array
    {
        $channelLinks = is_array($capture['channel_links'] ?? null) ? $capture['channel_links'] : [];
        $channels = is_array($summary['channels'] ?? null) ? $summary['channels'] : [];
        $configured = [];

        foreach ($channels as $channel) {
            if (! is_array($channel)) {
                continue;
            }

            $number = (int) ($channel['index'] ?? 0);

            if ($number < 1 || $number > 32) {
                continue;
            }

            $controls = is_array($channel['controls'] ?? null) ? $channel['controls'] : [];
            $icon = $channel['icon'] ?? null;
            $source = $channel['source'] ?? null;
            $dcaMembership = $channel['dca_membership'] ?? null;

            $configured[] = [
                'number' => $number,
                'name' => $this->fieldFromValue(
                    (string) ($channel['name'] ?? ''),
                    ! $this->isGenericStripName((string) ($channel['name'] ?? ''), $number, 'CH'),
                ),
                'colour' => $this->fieldFromValue(
                    $channel['color'] ?? null,
                    array_key_exists('color', $channel),
                ),
                'icon' => $this->fieldFromOptionalCapture(
                    $icon,
                    array_key_exists('icon', $channel) && $isLive,
                    $isLive ? 'not_queried' : 'fixture_transport',
                ),
                'mute' => $this->fieldFromValue(
                    (bool) ($channel['mute'] ?? false),
                    array_key_exists('mute', $channel),
                ),
                'fader' => $this->fieldFromValue(
                    $channel['fader'] ?? null,
                    array_key_exists('fader', $channel),
                ),
                'pan' => $this->fieldFromOptionalCapture(
                    $controls['pan'] ?? null,
                    array_key_exists('pan', $controls),
                    'not_queried',
                ),
                'stereo_link' => $this->fieldFromOptionalCapture(
                    $channelLinks[$number] ?? $controls['stereo_link'] ?? null,
                    ($channelLinks[$number] ?? null) !== null || array_key_exists('stereo_link', $controls),
                    $isLive ? 'not_queried' : 'fixture_transport',
                ),
                'dca_membership' => is_array($dcaMembership)
                    ? $this->learnedField($dcaMembership, true)
                    : $this->notLearned($isLive ? 'osc_path_not_queried' : 'fixture_transport'),
                'source_reference' => $this->buildChannelSourceReference($number, $summary, $source, $isLive),
                'processing' => [
                    'gate_on' => $this->fieldFromOptionalCapture($controls['gate_on'] ?? null, array_key_exists('gate_on', $controls)),
                    'compressor_on' => $this->fieldFromOptionalCapture($controls['compressor_on'] ?? null, array_key_exists('compressor_on', $controls)),
                    'eq_on' => $this->fieldFromOptionalCapture($controls['eq_on'] ?? null, array_key_exists('eq_on', $controls)),
                    'main_lr' => $this->fieldFromOptionalCapture($controls['main_lr'] ?? null, array_key_exists('main_lr', $controls)),
                ],
            ];
        }

        return $configured;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $capture
     * @return list<array<string, mixed>>
     */
    private function buildBuses(array $summary, array $capture, bool $isLive): array
    {
        $busLinks = is_array($capture['bus_links'] ?? null) ? $capture['bus_links'] : [];
        $buses = is_array($summary['buses'] ?? null) ? $summary['buses'] : [];
        $configured = [];

        foreach ($buses as $bus) {
            if (! is_array($bus)) {
                continue;
            }

            $number = (int) ($bus['index'] ?? 0);

            if ($number < 1 || $number > 16) {
                continue;
            }

            $name = (string) ($bus['name'] ?? '');
            $purpose = $this->inferBusPurpose($name);

            $configured[] = [
                'number' => $number,
                'name' => $this->fieldFromValue($name, ! $this->isGenericStripName($name, $number, 'Bus')),
                'mute' => $this->fieldFromValue((bool) ($bus['mute'] ?? false), array_key_exists('mute', $bus)),
                'fader' => $this->fieldFromValue($bus['fader'] ?? null, array_key_exists('fader', $bus)),
                'colour' => $this->fieldFromValue($bus['color'] ?? null, array_key_exists('color', $bus)),
                'stereo_link' => $this->fieldFromOptionalCapture(
                    $busLinks[$number] ?? null,
                    ($busLinks[$number] ?? null) !== null,
                    $isLive ? 'not_queried' : 'fixture_transport',
                ),
                'purpose' => $purpose !== null
                    ? $this->learnedField($purpose, true, reason: 'inferred_from_name')
                    : $this->notLearned('purpose_not_inferable'),
                'output_assignment' => $this->buildBusOutputReference($number, $summary),
            ];
        }

        return $configured;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array<string, mixed>>
     */
    private function buildDcas(array $summary, bool $isLive): array
    {
        $dcas = is_array($summary['dcas'] ?? null) ? $summary['dcas'] : [];
        $configured = [];

        foreach ($dcas as $dca) {
            if (! is_array($dca)) {
                continue;
            }

            $number = (int) ($dca['index'] ?? 0);

            if ($number < 1 || $number > 8) {
                continue;
            }

            $name = (string) ($dca['name'] ?? '');
            $nameLearned = ($dca['name_learned'] ?? false) === true
                || ($isLive && ! $this->isGenericStripName($name, $number, 'DCA'));

            $configured[] = [
                'number' => $number,
                'name' => $this->fieldFromValue($name, $nameLearned),
                'mute' => $this->fieldFromValue((bool) ($dca['mute'] ?? false), array_key_exists('mute', $dca)),
                'fader' => $this->fieldFromValue($dca['fader'] ?? null, array_key_exists('fader', $dca)),
                'colour' => $this->fieldFromOptionalCapture($dca['color'] ?? null, ($dca['color_learned'] ?? false) === true),
                'membership' => is_array($dca['membership'] ?? null)
                    ? $this->learnedField($dca['membership'], true)
                    : $this->notLearned($isLive ? 'membership_not_derived' : 'fixture_transport'),
            ];
        }

        return $configured;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array<string, mixed>>
     */
    private function buildMatrices(array $summary, bool $isLive): array
    {
        $matrices = is_array($summary['matrices'] ?? null) ? $summary['matrices'] : [];
        $configured = [];

        foreach ($matrices as $matrix) {
            if (! is_array($matrix)) {
                continue;
            }

            $number = (int) ($matrix['index'] ?? 0);

            if ($number < 1 || $number > 6) {
                continue;
            }

            $name = (string) ($matrix['name'] ?? '');
            $nameLearned = ($matrix['name_learned'] ?? false) === true
                || ($isLive && ! $this->isGenericStripName($name, $number, 'MTRX'));

            $configured[] = [
                'number' => $number,
                'name' => $this->fieldFromValue($name, $nameLearned),
                'mute' => $this->fieldFromValue((bool) ($matrix['mute'] ?? false), array_key_exists('mute', $matrix)),
                'fader' => $this->fieldFromValue($matrix['fader'] ?? null, array_key_exists('fader', $matrix)),
                'sources' => $this->notLearned('matrix_source_routing_not_in_configuration_scope'),
            ];
        }

        return $configured;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function buildFx(array $summary, bool $isLive): array
    {
        if ($isLive) {
            return [
                'learned' => false,
                'reason' => 'not_implemented',
                'slots' => [],
            ];
        }

        return [
            'learned' => false,
            'reason' => 'fixture_transport_not_configuration_learned',
            'slots' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $capture
     * @return array<string, mixed>
     */
    private function buildGlobals(array $summary, array $capture, bool $isLive): array
    {
        $identityCapture = is_array($capture['identity'] ?? null) ? $capture['identity'] : [];

        return [
            'sample_rate' => $identityCapture['sample_rate'] ?? $this->notLearned(
                $isLive ? 'not_queried' : 'fixture_transport',
            ),
            'clock_source' => $identityCapture['clock_source'] ?? $this->notLearned(
                $isLive ? 'not_queried' : 'fixture_transport',
            ),
            'firmware' => $identityCapture['firmware'] ?? $this->notLearned('info_query_not_implemented'),
            'model' => $this->learnedField(
                strtoupper((string) ($summary['console_type'] ?? 'x32')),
                true,
                reason: 'device_configuration',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChannelSourceReference(int $channelNumber, array $summary, mixed $deskSource, bool $isLive): array
    {
        $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];
        $normalized = is_array($routing['normalized'] ?? null) ? $routing['normalized'] : [];
        $inputBanks = is_array($normalized['input_banks'] ?? null) ? $normalized['input_banks'] : [];

        foreach ($inputBanks as $bank) {
            if (! is_array($bank)) {
                continue;
            }

            $channels = $bank['console_channels'] ?? [];

            if (! is_array($channels) || ! in_array($channelNumber, $channels, true)) {
                continue;
            }

            return $this->learnedField([
                'domain' => 'routing.normalized.input_banks',
                'bank' => $bank['bank'] ?? null,
                'source_type' => $bank['source_type'] ?? null,
                'source_range' => $bank['source_range'] ?? null,
            ], true);
        }

        if ($deskSource !== null) {
            return $this->learnedField([
                'domain' => 'desk.config.source',
                'raw_index' => $deskSource,
            ], true);
        }

        return $this->notLearned($isLive ? 'routing_and_desk_source_unavailable' : 'fixture_transport');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBusOutputReference(int $busNumber, array $summary): array
    {
        $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];
        $normalized = is_array($routing['normalized'] ?? null) ? $routing['normalized'] : [];
        $outBanks = is_array($normalized['out_1_16'] ?? null) ? $normalized['out_1_16'] : [];

        foreach ($outBanks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $rawLabel = strtoupper((string) ($block['raw_label'] ?? ''));

            if (! str_contains($rawLabel, sprintf('BUS %02d', $busNumber)) && ! str_contains($rawLabel, sprintf('BUS %d', $busNumber))) {
                continue;
            }

            return $this->learnedField([
                'domain' => 'routing.normalized.out_1_16',
                'block' => $block['block'] ?? null,
                'output_range' => $block['output_range'] ?? null,
                'source_range' => $block['source_range'] ?? null,
            ], true);
        }

        return $this->notLearned('output_assignment_not_in_learned_routing');
    }

    /**
     * @return array{value: mixed, state: string, reason?: string|null}
     */
    private function learnedField(mixed $value, bool $learned, ?string $reason = null): array
    {
        if (! $learned) {
            return $this->notLearned($reason ?? 'not_learned');
        }

        return [
            'value' => $value,
            'state' => 'learned',
            'reason' => $reason,
        ];
    }

    /**
     * @return array{value: null, state: string, reason: string}
     */
    private function notLearned(string $reason): array
    {
        return [
            'value' => null,
            'state' => 'not_learned',
            'reason' => $reason,
        ];
    }

    /**
     * @return array{value: mixed, state: string, reason?: string|null}
     */
    private function fieldFromValue(mixed $value, bool $learned, ?string $reason = null): array
    {
        if (! $learned || ($value === null || $value === '')) {
            return $this->notLearned($reason ?? 'not_learned');
        }

        return $this->learnedField($value, true, $reason);
    }

    /**
     * @return array{value: mixed, state: string, reason?: string|null}
     */
    private function fieldFromOptionalCapture(mixed $value, bool $learned, string $missingReason = 'not_queried'): array
    {
        if (! $learned) {
            return $this->notLearned($missingReason);
        }

        return $this->learnedField($value, true);
    }

    private function isGenericStripName(string $name, int $number, string $prefix): bool
    {
        $normalized = trim($name);

        return preg_match('/^'.preg_quote($prefix, '/').'\s*0?'.$number.'$/i', $normalized) === 1
            || preg_match('/^'.preg_quote($prefix, '/').'\s*'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'$/i', $normalized) === 1;
    }

    private function inferBusPurpose(string $name): ?string
    {
        $normalized = mb_strtolower(trim($name));

        if ($normalized === '' || preg_match('/^bus\s*\d+$/i', $normalized) === 1) {
            return null;
        }

        return $name;
    }

    /**
     * @return list<int>
     */
    public static function decodeDcaMembershipBitmap(int $bitmap): array
    {
        $members = [];

        for ($bit = 0; $bit < 8; $bit++) {
            if (($bitmap & (1 << $bit)) !== 0) {
                $members[] = $bit + 1;
            }
        }

        return $members;
    }
}
