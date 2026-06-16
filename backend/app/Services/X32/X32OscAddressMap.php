<?php

namespace App\Services\X32;

/**
 * Canonical X32/M32 OSC address paths for console workspace parameters.
 *
 * @see https://behringer.com/x32
 */
class X32OscAddressMap
{
    public static function channelFader(int $index): string
    {
        return sprintf('/ch/%02d/mix/fader', self::clamp($index, 1, 32));
    }

    public static function channelOn(int $index): string
    {
        return sprintf('/ch/%02d/mix/on', self::clamp($index, 1, 32));
    }

    public static function channelName(int $index): string
    {
        return sprintf('/ch/%02d/config/name', self::clamp($index, 1, 32));
    }

    public static function channelColor(int $index): string
    {
        return sprintf('/ch/%02d/config/color', self::clamp($index, 1, 32));
    }

    public static function channelPan(int $index): string
    {
        return sprintf('/ch/%02d/mix/pan', self::clamp($index, 1, 32));
    }

    public static function channelLr(int $index): string
    {
        return self::channelMixSt($index);
    }

    /**
     * Main stereo bus assignment for a channel (X32 OSC: mix/st, not mix/lr).
     */
    public static function channelMixSt(int $index): string
    {
        return sprintf('/ch/%02d/mix/st', self::clamp($index, 1, 32));
    }

    public static function channelGateOn(int $index): string
    {
        return sprintf('/ch/%02d/gate/on', self::clamp($index, 1, 32));
    }

    public static function channelDynOn(int $index): string
    {
        return sprintf('/ch/%02d/dyn/on', self::clamp($index, 1, 32));
    }

    public static function channelEqOn(int $index): string
    {
        return sprintf('/ch/%02d/eq/on', self::clamp($index, 1, 32));
    }

    public static function busFader(int $index): string
    {
        return sprintf('/bus/%02d/mix/fader', self::clamp($index, 1, 16));
    }

    public static function busOn(int $index): string
    {
        return sprintf('/bus/%02d/mix/on', self::clamp($index, 1, 16));
    }

    public static function busName(int $index): string
    {
        return sprintf('/bus/%02d/config/name', self::clamp($index, 1, 16));
    }

    public static function busColor(int $index): string
    {
        return sprintf('/bus/%02d/config/color', self::clamp($index, 1, 16));
    }

    public static function dcaFader(int $index): string
    {
        return sprintf('/dca/%d/fader', self::clamp($index, 1, 8));
    }

    public static function dcaOn(int $index): string
    {
        return sprintf('/dca/%d/on', self::clamp($index, 1, 8));
    }

    public static function matrixFader(int $index): string
    {
        return sprintf('/mtx/%02d/mix/fader', self::clamp($index, 1, 6));
    }

    public static function matrixOn(int $index): string
    {
        return sprintf('/mtx/%02d/mix/on', self::clamp($index, 1, 6));
    }

    public static function fxReturnLevel(int $index): string
    {
        return sprintf('/fxrtn/%02d/mix/fader', self::clamp($index, 1, 8));
    }

    public static function sceneRecall(): string
    {
        return '/-action/goscene';
    }

    /**
     * Scene library name for an operator-facing scene number (1–100).
     */
    public static function sceneShowfileName(int $operatorSceneNumber): string
    {
        return sprintf('/-show/showfile/scene/%03d/name', self::clamp($operatorSceneNumber, 1, 100));
    }

    public static function channelIcon(int $index): string
    {
        return sprintf('/ch/%02d/config/icon', self::clamp($index, 1, 32));
    }

    public static function channelSource(int $index): string
    {
        return sprintf('/ch/%02d/config/source', self::clamp($index, 1, 32));
    }

    public static function channelDcaGroup(int $index): string
    {
        return sprintf('/ch/%02d/grp/dca', self::clamp($index, 1, 32));
    }

    public static function channelLink(int $firstChannel): string
    {
        $first = self::clamp($firstChannel, 1, 31);
        $second = min(32, $first + 1);

        return sprintf('/config/chlink/%d-%d', $first, $second);
    }

    public static function busIcon(int $index): string
    {
        return sprintf('/bus/%02d/config/icon', self::clamp($index, 1, 16));
    }

    public static function busLink(int $firstBus): string
    {
        $first = self::clamp($firstBus, 1, 15);
        $second = min(16, $first + 1);

        return sprintf('/config/buslink/%d-%d', $first, $second);
    }

    public static function dcaName(int $index): string
    {
        return sprintf('/dca/%d/config/name', self::clamp($index, 1, 8));
    }

    public static function dcaColor(int $index): string
    {
        return sprintf('/dca/%d/config/color', self::clamp($index, 1, 8));
    }

    public static function matrixName(int $index): string
    {
        return sprintf('/mtx/%02d/config/name', self::clamp($index, 1, 6));
    }

    public static function clockRate(): string
    {
        return '/-prefs/clockrate';
    }

    public static function clockSource(): string
    {
        return '/-prefs/clocksource';
    }

    /**
     * @return array{layer: string, index: int, parameter: string}|null
     */
    public static function parsePath(string $path): ?array
    {
        if (preg_match('#^/ch/(\d{2})/mix/(fader|on)$#', $path, $m)) {
            return [
                'layer' => 'channels',
                'index' => (int) $m[1],
                'parameter' => $m[2] === 'fader' ? 'fader' : 'mute',
            ];
        }

        if (preg_match('#^/bus/(\d{2})/mix/(fader|on)$#', $path, $m)) {
            return [
                'layer' => 'buses',
                'index' => (int) $m[1],
                'parameter' => $m[2] === 'fader' ? 'fader' : 'mute',
            ];
        }

        if (preg_match('#^/dca/(\d)/(fader|on)$#', $path, $m)) {
            return [
                'layer' => 'dcas',
                'index' => (int) $m[1],
                'parameter' => $m[2] === 'fader' ? 'fader' : 'mute',
            ];
        }

        if (preg_match('#^/mtx/(\d{2})/mix/(fader|on)$#', $path, $m)) {
            return [
                'layer' => 'matrices',
                'index' => (int) $m[1],
                'parameter' => $m[2] === 'fader' ? 'fader' : 'mute',
            ];
        }

        return null;
    }

    private static function clamp(int $index, int $min, int $max): int
    {
        return min($max, max($min, $index));
    }
}
