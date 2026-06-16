<?php

namespace App\Services\X32;

/**
 * Decodes X32 routing OSC integer enums into documented label strings.
 *
 * Unknown indices are preserved as UNKNOWN(n) — never discarded or guessed.
 *
 * @see docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md
 */
class X32RoutingEnumDecoder
{
    /** @var list<string> */
    private const INPUT_BANK_LABELS = [
        'AN1-8', 'AN9-16', 'AN17-24', 'AN25-32',
        'A1-8', 'A9-16', 'A17-24', 'A25-32', 'A33-40', 'A41-48',
        'B1-8', 'B9-16', 'B17-24', 'B25-32', 'B33-40', 'B41-48',
        'CARD1-8', 'CARD9-16', 'CARD17-24', 'CARD25-32',
        'UIN1-8', 'UIN9-16', 'UIN17-24', 'UIN25-32',
    ];

    /** @var list<string> */
    private const EIGHT_WIDE_BLOCK_LABELS = [
        'AN1-8', 'AN9-16', 'AN17-24', 'AN25-32',
        'A1-8', 'A9-16', 'A17-24', 'A25-32', 'A33-40', 'A41-48',
        'B1-8', 'B9-16', 'B17-24', 'B25-32', 'B33-40', 'B41-48',
        'CARD1-8', 'CARD9-16', 'CARD17-24', 'CARD25-32',
        'OUT1-8', 'OUT9-16', 'P161-8', 'P169-16',
        'AUX1-6/Mon', 'AuxIN1-6/TB',
        'UOUT1-8', 'UOUT9-16', 'UOUT17-24', 'UOUT25-32', 'UOUT33-40', 'UOUT41-48',
        'UIN1-8', 'UIN9-16', 'UIN17-24', 'UIN25-32',
    ];

    /** @var list<string> */
    private const OUT_FOUR_WIDE_LOW_LABELS = [
        'AN1-4', 'AN9-12', 'AN17-20', 'AN25-28',
        'A1-4', 'A9-12', 'A17-20', 'A25-28', 'A33-36', 'A41-44',
        'B1-4', 'B9-12', 'B17-20', 'B25-28', 'B33-36', 'B41-44',
        'CARD1-4', 'CARD9-12', 'CARD17-20', 'CARD25-28',
        'OUT1-4', 'OUT9-12', 'P161-4', 'P169-12',
        'AUX/CR', 'AUX/TB',
        'UOUT1-4', 'UOUT9-12', 'UOUT17-20', 'UOUT25-28', 'UOUT33-36', 'UOUT41-44',
        'UIN1-4', 'UIN9-12', 'UIN17-20', 'UIN25-28',
    ];

    /** @var list<string> */
    private const OUT_FOUR_WIDE_HIGH_LABELS = [
        'AN5-8', 'AN13-16', 'AN21-24', 'AN29-32',
        'A5-8', 'A13-16', 'A21-24', 'A29-32', 'A37-40', 'A45-48',
        'B5-8', 'B13-16', 'B21-24', 'B29-32', 'B37-40', 'B45-48',
        'CARD5-8', 'CARD13-16', 'CARD21-24', 'CARD29-32',
        'OUT5-8', 'OUT13-16', 'P165-8', 'P1613-16',
        'AUX/CR', 'AUX/TB',
        'UOUT5-8', 'UOUT13-16', 'UOUT21-24', 'UOUT29-32', 'UOUT37-40', 'UOUT45-48',
        'UIN5-8', 'UIN13-16', 'UIN21-24', 'UIN29-32',
    ];

    public function inputBankLabel(int $index): string
    {
        return $this->labelAt(self::INPUT_BANK_LABELS, $index);
    }

    public function eightWideBlockLabel(int $index): string
    {
        return $this->labelAt(self::EIGHT_WIDE_BLOCK_LABELS, $index);
    }

    public function outBankLabel(int $index, bool $fourWideLow): string
    {
        $labels = $fourWideLow ? self::OUT_FOUR_WIDE_LOW_LABELS : self::OUT_FOUR_WIDE_HIGH_LABELS;

        return $this->labelAt($labels, $index);
    }

    public function outBankLabelForPath(int $index, string $oscPath): string
    {
        return $this->outBankLabel($index, $oscPath, X32RoutingOscAddressMap::usesOutBankFourWideEnum($oscPath));
    }

    public function routswitchMode(int $index): string
    {
        return match ($index) {
            0 => 'rec',
            1 => 'play',
            default => 'unknown',
        };
    }

    /**
     * Decode /outputs/main/NN/src assignment index.
     *
     * @see docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md §3.15
     */
    public function outputMainSrcLabel(int $index): string
    {
        if ($index === 0) {
            return 'OFF';
        }

        if ($index === 1) {
            return 'Main L';
        }

        if ($index === 2) {
            return 'Main R';
        }

        if ($index === 3) {
            return 'M/C';
        }

        if ($index >= 4 && $index <= 19) {
            return sprintf('MixBus %02d', $index - 3);
        }

        if ($index >= 20 && $index <= 25) {
            return sprintf('Matrix %d', $index - 19);
        }

        return sprintf('UNKNOWN(%d)', $index);
    }

    public function isMainLeftLabel(string $label): bool
    {
        return $label === 'Main L';
    }

    public function isMainRightLabel(string $label): bool
    {
        return $label === 'Main R';
    }

    /**
     * @return array{category: string, family: string}
     */
    public function classifySourceLabel(string $label): array
    {
        if (str_starts_with($label, 'UNKNOWN')) {
            return ['category' => 'unknown', 'family' => $label];
        }

        if (str_starts_with($label, 'AN')) {
            return ['category' => 'local', 'family' => 'local_analog'];
        }

        if (str_starts_with($label, 'CARD')) {
            return ['category' => 'card_usb', 'family' => 'card_usb'];
        }

        if (str_starts_with($label, 'UIN')) {
            return ['category' => 'user_in', 'family' => 'user_in'];
        }

        if (str_starts_with($label, 'UOUT')) {
            return ['category' => 'user_out', 'family' => 'user_out'];
        }

        if (str_starts_with($label, 'OUT')) {
            return ['category' => 'out_bank', 'family' => 'out_1_16'];
        }

        if (str_starts_with($label, 'P16')) {
            return ['category' => 'p16', 'family' => 'p16_ultranet'];
        }

        if (str_starts_with($label, 'AUX') || str_starts_with($label, 'AuxIN')) {
            return ['category' => 'aux', 'family' => 'aux'];
        }

        if (preg_match('/^A\d/', $label) === 1) {
            return ['category' => 'aes50_a', 'family' => 'aes50_a'];
        }

        if (preg_match('/^B\d/', $label) === 1) {
            return ['category' => 'aes50_b', 'family' => 'aes50_b'];
        }

        return ['category' => 'other', 'family' => $label];
    }

    /**
     * @param  list<string>  $labels
     */
    private function labelAt(array $labels, int $index): string
    {
        return $labels[$index] ?? sprintf('UNKNOWN(%d)', $index);
    }
}
