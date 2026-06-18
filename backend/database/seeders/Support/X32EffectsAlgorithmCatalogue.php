<?php

namespace Database\Seeders\Support;

use App\Enums\X32SlotGroup;

/**
 * Maillot-verified X32/M32 FX algorithm catalogue (PH044).
 *
 * @see docs/x32/PH044_EFFECTS_ALGORITHM_CATALOGUE.md
 */
final class X32EffectsAlgorithmCatalogue
{
    /**
     * @return array<int, array{int, string, string, string}>
     */
    public static function fx1To4Algorithms(): array
    {
        return [
            [0, 'HALL', 'Hall Reverb', 'hall'],
            [1, 'AMBI', 'Ambiance', 'reverb'],
            [2, 'RPLT', 'Rich Plate Reverb', 'plate'],
            [3, 'ROOM', 'Room Reverb', 'room'],
            [4, 'CHAM', 'Chamber Reverb', 'room'],
            [5, 'PLAT', 'Plate Reverb', 'plate'],
            [6, 'VREV', 'Vintage Reverb', 'reverb'],
            [7, 'VRM', 'Vintage Room', 'room'],
            [8, 'GATE', 'Gated Reverb', 'special_fx'],
            [9, 'RVRS', 'Reverse Reverb', 'special_fx'],
            [10, 'DLY', 'Stereo Delay', 'delay'],
            [11, '3TAP', 'Triple Delay', 'delay'],
            [12, '4TAP', '4-Tap Delay', 'delay'],
            [13, 'CRS', 'Stereo Chorus', 'chorus'],
            [14, 'FLNG', 'Stereo Flanger', 'flanger'],
            [15, 'PHAS', 'Stereo Phaser', 'flanger'],
            [16, 'DIMC', 'Dimensional Chorus', 'chorus'],
            [17, 'FILT', 'Mood Filter', 'special_fx'],
            [18, 'ROTA', 'Rotary Speaker', 'special_fx'],
            [19, 'PAN', 'Tremolo/Panner', 'special_fx'],
            [20, 'SUB', 'Suboctaver', 'special_fx'],
            [21, 'D/RV', 'Delay + Chamber', 'delay'],
            [22, 'CR/R', 'Chorus + Chamber', 'chorus'],
            [23, 'FL/R', 'Flanger + Chamber', 'flanger'],
            [24, 'D/CR', 'Delay + Chorus', 'delay'],
            [25, 'D/FL', 'Delay + Flanger', 'delay'],
            [26, 'MODD', 'Modulation Delay', 'dub_delay'],
            [27, 'GEQ2', 'Dual Graphic EQ', 'graphic_eq'],
            [28, 'GEQ', 'Stereo Graphic EQ', 'graphic_eq'],
            [29, 'TEQ2', 'Dual TrueEQ', 'graphic_eq'],
            [30, 'TEQ', 'Stereo TrueEQ', 'graphic_eq'],
            [31, 'DES2', 'Dual DeEsser', 'enhancer'],
            [32, 'DES', 'Stereo DeEsser', 'enhancer'],
            [33, 'P1A', 'Stereo Xtec EQ1', 'graphic_eq'],
            [34, 'P1A2', 'Dual Xtec EQ1', 'graphic_eq'],
            [35, 'PQ5', 'Stereo Xtec EQ5', 'graphic_eq'],
            [36, 'PQ5S', 'Dual Xtec EQ5', 'graphic_eq'],
            [37, 'WAVD', 'Wave Designer', 'special_fx'],
            [38, 'LIM', 'Precision Limiter', 'limiter'],
            [39, 'CMB', 'Combinator', 'compressor'],
            [40, 'CMB2', 'Dual Combinator', 'compressor'],
            [41, 'FAC', 'Fair Comp', 'compressor'],
            [42, 'FAC1M', 'M/S Fair Comp', 'compressor'],
            [43, 'FAC2', 'Dual Fair Comp', 'compressor'],
            [44, 'LEC', 'Leisure Comp', 'compressor'],
            [45, 'LEC2', 'Dual Leisure Comp', 'compressor'],
            [46, 'ULC', 'Ultimo Comp', 'compressor'],
            [47, 'ULC2', 'Dual Ultimo Comp', 'compressor'],
            [48, 'ENH2', 'Dual Enhancer', 'enhancer'],
            [49, 'ENH', 'Stereo Enhancer', 'enhancer'],
            [50, 'EXC2', 'Dual Exciter', 'enhancer'],
            [51, 'EXC', 'Stereo Exciter', 'enhancer'],
            [52, 'IMG', 'Stereo Imager', 'enhancer'],
            [53, 'EDI', 'Edison EX1', 'special_fx'],
            [54, 'SON', 'Sound Maxer', 'special_fx'],
            [55, 'AMP2', 'Dual Guitar Amp', 'special_fx'],
            [56, 'AMP', 'Stereo Guitar Amp', 'special_fx'],
            [57, 'DRV2', 'Dual Tube Stage', 'special_fx'],
            [58, 'DRV', 'Stereo Tube Stage', 'special_fx'],
            [59, 'PIT2', 'Dual Pitch Shifter', 'special_fx'],
            [60, 'PIT', 'Stereo Pitch', 'special_fx'],
        ];
    }

    /**
     * @return array<int, array{int, string, string, string}>
     */
    public static function fx5To8Algorithms(): array
    {
        return [
            [0, 'GEQ2', 'Dual Graphic EQ', 'graphic_eq'],
            [1, 'GEQ', 'Stereo Graphic EQ', 'graphic_eq'],
            [2, 'TEQ2', 'Dual TrueEQ', 'graphic_eq'],
            [3, 'TEQ', 'Stereo TrueEQ', 'graphic_eq'],
            [4, 'DES2', 'Dual DeEsser', 'enhancer'],
            [5, 'DES', 'Stereo DeEsser', 'enhancer'],
            [6, 'P1A', 'Stereo Xtec EQ1', 'graphic_eq'],
            [7, 'P1A2', 'Dual Xtec EQ1', 'graphic_eq'],
            [8, 'PQ5', 'Stereo Xtec EQ5', 'graphic_eq'],
            [9, 'PQ5S', 'Dual Xtec EQ5', 'graphic_eq'],
            [10, 'WAVD', 'Wave Designer', 'special_fx'],
            [11, 'LIM', 'Precision Limiter', 'limiter'],
            [12, 'FAC', 'Fair Comp', 'compressor'],
            [13, 'FAC1M', 'M/S Fair Comp', 'compressor'],
            [14, 'FAC2', 'Dual Fair Comp', 'compressor'],
            [15, 'LEC', 'Leisure Comp', 'compressor'],
            [16, 'LEC2', 'Dual Leisure Comp', 'compressor'],
            [17, 'ULC', 'Ultimo Comp', 'compressor'],
            [18, 'ULC2', 'Dual Ultimo Comp', 'compressor'],
            [19, 'ENH2', 'Dual Enhancer', 'enhancer'],
            [20, 'ENH', 'Stereo Enhancer', 'enhancer'],
            [21, 'EXC2', 'Dual Exciter', 'enhancer'],
            [22, 'EXC', 'Stereo Exciter', 'enhancer'],
            [23, 'IMG', 'Stereo Imager', 'enhancer'],
            [24, 'EDI', 'Edison EX1', 'special_fx'],
            [25, 'SON', 'Sound Maxer', 'special_fx'],
            [26, 'AMP2', 'Dual Guitar Amp', 'special_fx'],
            [27, 'AMP', 'Stereo Guitar Amp', 'special_fx'],
            [28, 'DRV2', 'Dual Tube Stage', 'special_fx'],
            [29, 'DRV', 'Stereo Tube Stage', 'special_fx'],
            [30, 'PHAS', 'Stereo Phaser', 'flanger'],
            [31, 'FILT', 'Mood Filter', 'special_fx'],
            [32, 'PAN', 'Tremolo/Panner', 'special_fx'],
            [33, 'SUB', 'Suboctaver', 'special_fx'],
        ];
    }

    public static function slotGroupForFx1To4(): string
    {
        return X32SlotGroup::Fx1To4->value;
    }

    public static function slotGroupForFx5To8(): string
    {
        return X32SlotGroup::Fx5To8->value;
    }
}
