<?php

namespace App\Services\Console;

use App\DataTransferObjects\Console\VirtualConsoleStrip;
use App\Models\ConsoleLearningSnapshot;
use App\Services\X32\X32FaderScale;
use App\Services\X32\X32InputChannelControlMap;
use App\Services\X32\X32StripLabelHelper;

/**
 * Builds exactly 32 VirtualConsoleStrip objects from learned baseline/snapshot data.
 */
class VirtualConsoleStripBuilder
{
    public function __construct(
        private readonly ShowConsoleStripEnricher $stripEnricher,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @return list<VirtualConsoleStrip>
     */
    public function build(array $summary, ?ConsoleLearningSnapshot $sourceSnapshot = null): array
    {
        $channels = $this->stripEnricher->enrich(
            $summary['channels'] ?? [],
            'channel',
            $sourceSnapshot,
        );

        $indexed = [];

        foreach ($channels as $channel) {
            $index = (int) ($channel['index'] ?? 0);

            if ($index >= X32InputChannelControlMap::CHANNEL_MIN && $index <= X32InputChannelControlMap::CHANNEL_MAX) {
                $indexed[$index] = $channel;
            }
        }

        $strips = [];

        for ($channelNumber = X32InputChannelControlMap::CHANNEL_MIN; $channelNumber <= X32InputChannelControlMap::CHANNEL_MAX; $channelNumber++) {
            $strips[] = $this->buildStrip($channelNumber, $indexed[$channelNumber] ?? []);
        }

        return $strips;
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    private function buildStrip(int $channelNumber, array $channel): VirtualConsoleStrip
    {
        $controls = is_array($channel['controls'] ?? null) ? $channel['controls'] : [];
        $rawName = trim((string) ($channel['name'] ?? ''));
        $displayName = X32StripLabelHelper::displayName($rawName, $channelNumber, 'CH');
        $name = $displayName ?? (string) $channelNumber;
        $faderLevel = min(1.0, max(0.0, (float) ($channel['fader'] ?? 0.0)));
        $meterLevel = $this->resolveMeterLevel($channel, $controls, $faderLevel);

        $state = [
            'muted' => (bool) ($channel['mute'] ?? false),
            'gain' => isset($controls['gain']) ? (float) $controls['gain'] : null,
            'phantom48v' => (bool) ($controls['phantom48v'] ?? false),
            'gateOn' => (bool) ($controls['gate_on'] ?? false),
            'compressorOn' => (bool) ($controls['compressor_on'] ?? false),
            'eqOn' => (bool) ($controls['eq_on'] ?? false),
            'sendsOpen' => (bool) ($controls['sends_open'] ?? false),
            'pan' => min(1.0, max(0.0, (float) ($controls['pan'] ?? 0.5))),
            'linked' => (bool) ($controls['stereo_link'] ?? false),
            'mainLr' => array_key_exists('main_lr', $controls) ? (bool) $controls['main_lr'] : true,
            'faderLevel' => $faderLevel,
            'meterLevel' => $meterLevel,
        ];

        return new VirtualConsoleStrip(
            id: $channelNumber,
            channelNumber: $channelNumber,
            oscChannelNumber: X32InputChannelControlMap::formatChannelNumber($channelNumber),
            name: $name,
            color: (int) ($channel['color'] ?? 0),
            icon: null,
            meterLevel: $meterLevel,
            muted: $state['muted'],
            gain: $state['gain'],
            phantom48v: $state['phantom48v'],
            gateOn: $state['gateOn'],
            compressorOn: $state['compressorOn'],
            eqOn: $state['eqOn'],
            sendsOpen: $state['sendsOpen'],
            pan: $state['pan'],
            linked: $state['linked'],
            mainLr: $state['mainLr'],
            faderLevel: $state['faderLevel'],
            lastConfirmedState: $state,
        );
    }

    /**
     * @param  array<string, mixed>  $channel
     * @param  array<string, mixed>  $controls
     */
    private function resolveMeterLevel(array $channel, array $controls, float $faderLevel): float
    {
        if (isset($controls['meter'])) {
            return min(1.0, max(0.0, (float) $controls['meter']));
        }

        if (isset($channel['meter'])) {
            return min(1.0, max(0.0, (float) $channel['meter']));
        }

        if ($faderLevel <= 0.0) {
            return 0.0;
        }

        $db = X32FaderScale::linearToDb($faderLevel);

        return min(1.0, max(0.0, ($db + 60.0) / 70.0));
    }
}
