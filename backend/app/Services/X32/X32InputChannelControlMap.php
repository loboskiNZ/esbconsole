<?php

namespace App\Services\X32;

/**
 * Canonical OSC control map for X32/M32 input channels 01–32.
 *
 * Single source of truth — do not scatter OSC paths in UI components.
 */
class X32InputChannelControlMap
{
    public const CHANNEL_MIN = 1;

    public const CHANNEL_MAX = 32;

    /** @var array<string, array<string, mixed>> */
    private const CONTROLS = [
        'fader' => [
            'label' => 'Fader',
            'osc_template' => '/ch/{NN}/mix/fader',
            'value_type' => 'float',
            'min' => 0.0,
            'max' => 1.0,
            'read' => true,
            'write' => true,
            'ui_transform' => 'fader_db',
        ],
        'mute' => [
            'label' => 'Mute',
            'osc_template' => '/ch/{NN}/mix/on',
            'value_type' => 'bool',
            'read' => true,
            'write' => true,
            'invert_osc' => true,
            'note' => 'X32 mix/on is inverse of UI mute (on=1 means unmuted).',
        ],
        'pan' => [
            'label' => 'Pan',
            'osc_template' => '/ch/{NN}/mix/pan',
            'value_type' => 'float',
            'min' => 0.0,
            'max' => 1.0,
            'read' => true,
            'write' => true,
            'ui_transform' => 'pan_lr',
        ],
        'main_lr' => [
            'label' => 'Main L/R',
            'osc_template' => '/ch/{NN}/mix/st',
            'value_type' => 'bool',
            'read' => true,
            'write' => true,
            'note' => 'X32 main stereo bus assignment — OSC path is /mix/st (0=off, 1=on).',
        ],
        'gate_on' => [
            'label' => 'Gate',
            'osc_template' => '/ch/{NN}/gate/on',
            'value_type' => 'bool',
            'read' => true,
            'write' => true,
        ],
        'compressor_on' => [
            'label' => 'Compressor',
            'osc_template' => '/ch/{NN}/dyn/on',
            'value_type' => 'bool',
            'read' => true,
            'write' => true,
        ],
        'eq_on' => [
            'label' => 'EQ',
            'osc_template' => '/ch/{NN}/eq/on',
            'value_type' => 'bool',
            'read' => true,
            'write' => true,
        ],
        'sends' => [
            'label' => 'Sends',
            'value_type' => 'bool',
            'read' => true,
            'write' => false,
            'ui_only' => true,
            'note' => 'Opens/marks send controls for the channel; full bus send matrix not implemented.',
        ],
        'gain' => [
            'label' => 'Gain',
            'osc_template' => '/headamp/{NN}/gain',
            'value_type' => 'float',
            'min' => 0.0,
            'max' => 1.0,
            'read' => true,
            'write' => false,
            'headamp_dependent' => true,
            'ui_transform' => 'gain_db',
            'note' => 'Requires headamp/source mapping — X32 gain is addressed by headamp index, not channel number.',
        ],
        'phantom48v' => [
            'label' => '48V',
            'osc_template' => '/headamp/{NN}/phantom',
            'value_type' => 'bool',
            'read' => true,
            'write' => false,
            'headamp_dependent' => true,
            'note' => 'Requires headamp/source mapping — phantom is per headamp input, not channel strip.',
        ],
        'stereo_link' => [
            'label' => 'Link',
            'value_type' => 'bool',
            'read' => true,
            'write' => false,
            'ui_only' => true,
            'note' => 'Stereo link UI state only — channel-pair OSC link not implemented in this workspace.',
        ],
        'meter' => [
            'label' => 'Meter',
            'value_type' => 'float',
            'min' => 0.0,
            'max' => 1.0,
            'read' => true,
            'write' => false,
            'note' => 'Display-only; populated from learned/snapshot data when available.',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function controlKeys(): array
    {
        return array_keys(self::CONTROLS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function definition(string $controlKey): ?array
    {
        return self::CONTROLS[$controlKey] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return self::CONTROLS;
    }

    public static function oscPath(string $controlKey, int $channelNumber): ?string
    {
        $definition = self::definition($controlKey);

        if ($definition === null || empty($definition['osc_template'])) {
            return null;
        }

        if (! empty($definition['headamp_dependent'])) {
            return null;
        }

        return str_replace(
            '{NN}',
            self::formatChannelNumber($channelNumber),
            (string) $definition['osc_template'],
        );
    }

    public static function formatChannelNumber(int $channelNumber): string
    {
        return str_pad((string) self::clampChannel($channelNumber), 2, '0', STR_PAD_LEFT);
    }

    public static function clampChannel(int $channelNumber): int
    {
        return min(self::CHANNEL_MAX, max(self::CHANNEL_MIN, $channelNumber));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function headampDependentControls(): array
    {
        return array_values(array_filter(
            self::CONTROLS,
            static fn (array $definition): bool => ! empty($definition['headamp_dependent']),
        ));
    }
}
