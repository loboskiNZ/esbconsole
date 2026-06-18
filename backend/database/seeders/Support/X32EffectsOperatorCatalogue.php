<?php

namespace Database\Seeders\Support;

/**
 * Operator-friendly labels and guidance for X32 FX algorithms (PH044.05D).
 *
 * @see docs/x32/PH044_EFFECTS_ALGORITHM_CATALOGUE.md
 */
final class X32EffectsOperatorCatalogue
{
    /**
     * @return array<string, array{
     *     operator_name: string,
     *     operator_description: string,
     *     recommended_for_json: array<int, string>,
     *     operator_category: string,
     *     difficulty: string,
     *     starter_notes: string
     * }>
     */
    public static function metadataByCode(): array
    {
        return [
            'HALL' => self::meta('Big Hall', 'Large hall reverb for depth and space on vocals, drums, or keys.', ['Vocals', 'Horns', 'Drums', 'FOH'], 'Reverb', 'easy', 'Start with short pre-delay and moderate decay for live vocals.'),
            'AMBI' => self::meta('Ambience', 'Smooth ambience reverb for glue and width without washing out the mix.', ['Vocals', 'Backing Vocals', 'Horns', 'Drums'], 'Reverb', 'easy', 'Use lower level on busy songs; great on backing vocals.'),
            'RPLT' => self::meta('Rich Plate', 'Dense plate reverb with extra body for lead vocals and snare.', ['Vocals', 'Snare', 'Horns'], 'Reverb', 'medium', 'Pair with a short room or delay for modern vocal stacks.'),
            'ROOM' => self::meta('Tight Room', 'Short room reverb for intimacy and presence on vocals and horns.', ['Vocals', 'Horns', 'Drums'], 'Reverb', 'easy', 'Keep decay short so lyrics stay intelligible on stage.'),
            'CHAM' => self::meta('Chamber Reverb', 'Medium chamber character for warmth on vocals and acoustic sources.', ['Vocals', 'Horns', 'Backing Vocals'], 'Reverb', 'easy', 'Useful when hall is too big but plate is too bright.'),
            'PLAT' => self::meta('Vocal Plate', 'Classic plate reverb — the go-to vocal ambience for most shows.', ['Vocals', 'Backing Vocals', 'Horns'], 'Reverb', 'easy', 'Default vocal send effect; ride level per song.'),
            'VREV' => self::meta('Vintage Reverb', 'Lo-fi vintage reverb colour for character parts.', ['Vocals', 'Special FX'], 'Reverb', 'medium', 'Best for intro/outro moments or vintage song treatments.'),
            'VRM' => self::meta('Vintage Room', 'Smaller vintage room tone for retro vocal ambience.', ['Vocals', 'Special FX'], 'Reverb', 'medium', 'Try on featured vocal lines rather than full blend.'),
            'GATE' => self::meta('Gated Reverb', '80s-style gated reverb burst — dramatic on snare and FX hits.', ['Snare', 'Drums', 'Special FX'], 'Reverb', 'advanced', 'Trigger from snare send; shorten gate for tight pop mixes.'),
            'RVRS' => self::meta('Reverse Reverb', 'Reverse swell reverb for transitions and special moments.', ['Vocals', 'Special FX'], 'Reverb', 'advanced', 'Use sparingly before vocal entries or breakdowns.'),
            'DLY' => self::meta('Vocal Delay', 'Stereo delay for vocal depth, throws, and rhythmic echoes.', ['Vocals', 'Backing Vocals', 'Horns'], 'Delay', 'easy', 'Sync to song tempo; automate level for phrase endings.'),
            '3TAP' => self::meta('Triple Tap Delay', 'Three-tap delay pattern for rhythmic vocal and guitar effects.', ['Vocals', 'Horns', 'Special FX'], 'Delay', 'medium', 'Good for dub-style echoes and stacked repeats.'),
            '4TAP' => self::meta('Musical Multi-Tap Delay', 'Rhythm-synced multi-tap delay for musical repeats.', ['Vocals', 'Drums', 'Special FX'], 'Delay', 'medium', 'Set tap factors to match song subdivision.'),
            'CRS' => self::meta('Voice Doubler', 'Stereo chorus for width and vocal thickening.', ['Vocals', 'Backing Vocals', 'Horns'], 'Modulation', 'easy', 'Blend under the dry vocal — avoid obvious wobble on lead.'),
            'FLNG' => self::meta('Jet Flanger', 'Stereo flanger for movement on guitars, keys, and FX sends.', ['Horns', 'Special FX'], 'Modulation', 'medium', 'Sweep rate slow for pads; faster for accent moments.'),
            'PHAS' => self::meta('Funk Phaser', 'Stereo phaser for funk guitars, keys, and horn accents.', ['Horns', 'Special FX'], 'Modulation', 'medium', 'Classic on electric piano and rhythmic guitar parts.'),
            'DIMC' => self::meta('Wide Chorus', 'Dimension-style widening chorus for stereo spread.', ['Vocals', 'Backing Vocals', 'Special FX'], 'Modulation', 'easy', 'Subtle mix adds width without obvious modulation.'),
            'FILT' => self::meta('Radio Filter', 'Sweeping filter for lo-fi, radio, and breakdown effects.', ['Vocals', 'Special FX'], 'Special FX', 'medium', 'Automate filter speed for build-ups and breakdowns.'),
            'ROTA' => self::meta('Rotary Speaker', 'Rotary speaker simulation for organ and guitar movement.', ['Horns', 'Special FX'], 'Modulation', 'medium', 'Great on organ patches and sustained chords.'),
            'PAN' => self::meta('Auto Pan', 'Tremolo and auto-pan movement across the stereo field.', ['Special FX', 'Horns'], 'Modulation', 'easy', 'Use on percussion loops or synth pads for motion.'),
            'SUB' => self::meta('Sub Generator', 'Sub-octave generation for weight on bass and kick sources.', ['Drums', 'Special FX'], 'Special FX', 'advanced', 'High-pass the send; keep sub level controlled on small PA systems.'),
            'D/RV' => self::meta('Delay + Reverb', 'Combined delay and chamber reverb in one FX slot.', ['Vocals', 'Special FX'], 'Delay', 'medium', 'Useful when rack slots are limited.'),
            'CR/R' => self::meta('Chorus + Chamber', 'Chorus blended with chamber reverb for lush vocals.', ['Vocals', 'Horns'], 'Modulation', 'medium', 'Good for ballads and sustained vocal lines.'),
            'FL/R' => self::meta('Flanger + Chamber', 'Flanger with chamber tail for swirling ambience.', ['Horns', 'Special FX'], 'Modulation', 'advanced', 'Best on short horn stabs and accent phrases.'),
            'D/CR' => self::meta('Delay + Chorus', 'Delay and chorus combo for wide vocal ambience.', ['Vocals', 'Backing Vocals'], 'Delay', 'medium', 'Set delay time to song tempo before adjusting chorus depth.'),
            'D/FL' => self::meta('Delay + Flanger', 'Delay with flanger motion for synth and guitar sends.', ['Special FX', 'Horns'], 'Delay', 'advanced', 'Watch for buildup on dense arrangements.'),
            'MODD' => self::meta('Dub Delay', 'Modulation delay with reggae/dub character and ambience tail.', ['Vocals', 'Horns', 'Special FX'], 'Delay', 'medium', 'Classic on reggae and dub selections; sync delay time to feel.'),
            'GEQ2' => self::meta('Dual Graphic EQ', 'Two-channel graphic EQ for stereo bus or dual-mono correction.', ['FOH', 'Drums'], 'Utility', 'medium', 'Use on subgroups before main processing.'),
            'GEQ' => self::meta('Graphic EQ', '31-band graphic EQ for tonal shaping on buses and returns.', ['FOH', 'Drums', 'Horns'], 'Utility', 'medium', 'Cut problem frequencies before boosting; useful on FX returns.'),
            'TEQ2' => self::meta('Dual True EQ', 'Parametric true EQ on two channels for surgical tone control.', ['FOH'], 'Utility', 'advanced', 'Use narrow Q for feedback hunting on monitors or FOH.'),
            'TEQ' => self::meta('True EQ', 'Parametric true EQ for precise tonal balance.', ['FOH', 'Vocals'], 'Utility', 'advanced', 'Gentle cuts on muddy vocals; broad boosts sparingly.'),
            'DES2' => self::meta('Dual De-Esser', 'Two-channel de-esser for sibilance control on vocal pairs.', ['Vocals', 'Backing Vocals'], 'Dynamics', 'medium', 'Target 5–8 kHz range; avoid over-processing backing stacks.'),
            'DES' => self::meta('Vocal De-Esser', 'Sibilance control for lead and backing vocals.', ['Vocals', 'Backing Vocals'], 'Dynamics', 'easy', 'Insert or send; reduce harsh “S” without dulling the voice.'),
            'P1A' => self::meta('Xtec Program EQ', 'Musical program EQ for broad tonal colour on vocals or buses.', ['Vocals', 'FOH', 'Horns'], 'Utility', 'medium', 'Boost high shelf for air; low boost for warmth.'),
            'P1A2' => self::meta('Dual Xtec Program EQ', 'Dual program EQ for stereo bus tone shaping.', ['FOH'], 'Utility', 'medium', 'Use on drum bus or stereo instrument subgroups.'),
            'PQ5' => self::meta('Xtec Midrange EQ', 'Midrange-focused EQ for honk and presence control.', ['Vocals', 'Horns', 'FOH'], 'Utility', 'medium', 'Cut 400–800 Hz on muddy vocals; add horn bite around 2–4 kHz.'),
            'PQ5S' => self::meta('Dual Xtec Midrange EQ', 'Dual midrange EQ for stereo bus correction.', ['FOH', 'Horns'], 'Utility', 'medium', 'Pair with graphic EQ for full tonal control.'),
            'WAVD' => self::meta('Drum Punch', 'Transient shaping for drum attack and punch.', ['Drums', 'Snare', 'Hi-Hat'], 'Dynamics', 'medium', 'Enhance snare crack; watch overhead mics for harshness.'),
            'LIM' => self::meta('Precision Limiter', 'Brickwall-style limiter for peak control on buses.', ['FOH', 'Drums'], 'Dynamics', 'medium', 'Use gentle squeeze on drum bus; avoid heavy limiting on vocals.'),
            'CMB' => self::meta('Combinator', 'Multi-band compression and tone shaping in one processor.', ['FOH', 'Drums'], 'Dynamics', 'advanced', 'Start with factory-style gentle settings on main bus.'),
            'CMB2' => self::meta('Dual Combinator', 'Dual-channel combinator for stereo bus dynamics.', ['FOH'], 'Dynamics', 'advanced', 'For experienced engineers; verify gain staging on PA.'),
            'FAC' => self::meta('Fair Compressor', 'Musical compressor for vocals, drums, and bass control.', ['Vocals', 'Drums', 'FOH'], 'Dynamics', 'easy', '2:1–4:1 ratio for vocal glue; slower attack on drums.'),
            'FAC1M' => self::meta('M/S Fair Compressor', 'Mid/side fair compressor for stereo bus width and control.', ['FOH'], 'Dynamics', 'advanced', 'Adjust side compression carefully to preserve stereo image.'),
            'FAC2' => self::meta('Dual Fair Compressor', 'Dual fair compressor for paired channels or stereo buses.', ['FOH', 'Drums'], 'Dynamics', 'medium', 'Link channels when compressing stereo sources.'),
            'LEC' => self::meta('Leisure Compressor', 'Smooth leisure-style compressor for vocal and bus leveling.', ['Vocals', 'FOH'], 'Dynamics', 'easy', 'Gentle gain reduction for evening-out dynamic singers.'),
            'LEC2' => self::meta('Dual Leisure Compressor', 'Dual leisure compressor for stereo vocal or bus pairs.', ['Vocals', 'FOH'], 'Dynamics', 'medium', 'Match attack/release on both channels for stereo sources.'),
            'ULC' => self::meta('Ultimo Compressor', 'Aggressive ultimo compressor for tight drum and bass control.', ['Drums', 'FOH'], 'Dynamics', 'advanced', 'Fast attack for peak control; ease off on live vocals.'),
            'ULC2' => self::meta('Dual Ultimo Compressor', 'Dual ultimo compressor for stereo bus peak management.', ['FOH', 'Drums'], 'Dynamics', 'advanced', 'Reserve for drum subgroups or main bus protection.'),
            'ENH2' => self::meta('Dual Enhancer', 'Dual harmonic enhancer for stereo bus clarity.', ['FOH', 'Vocals'], 'Dynamics', 'medium', 'Add brightness without harsh EQ boosts.'),
            'ENH' => self::meta('Stereo Enhancer', 'Harmonic enhancer for presence and clarity.', ['Vocals', 'FOH'], 'Dynamics', 'easy', 'Subtle settings on lead vocal send for air and cut.'),
            'EXC2' => self::meta('Dual Exciter', 'Dual exciter for stereo top-end sparkle.', ['FOH', 'Vocals'], 'Dynamics', 'medium', 'Use on dull mixes; avoid on already-bright PA systems.'),
            'EXC' => self::meta('Stereo Exciter', 'High-frequency exciter for detail and shimmer.', ['Vocals', 'Horns'], 'Dynamics', 'medium', 'Blend lightly on vocals and horn bus.'),
            'IMG' => self::meta('Stereo Imager', 'Stereo width and image control for buses.', ['FOH'], 'Utility', 'medium', 'Widen keyboards and backing tracks; keep lead vocal mono-focused.'),
            'EDI' => self::meta('Edison Stereo Tool', 'Stereo editing and phase tools for problem-solving.', ['FOH', 'Special FX'], 'Utility', 'advanced', 'Check mono compatibility after widening.'),
            'SON' => self::meta('Sound Maximizer', 'Loudness maximizer for mix density on buses.', ['FOH'], 'Dynamics', 'advanced', 'Use sparingly live — protect headroom for FOH engineer.'),
            'AMP2' => self::meta('Dual Guitar Amp', 'Dual guitar amp simulation for stereo guitar rigs.', ['Special FX'], 'Special FX', 'medium', 'Mic-style amp tone without a physical amp on stage.'),
            'AMP' => self::meta('Guitar Amp', 'Guitar amp simulation for direct guitar warmth and drive.', ['Special FX'], 'Special FX', 'medium', 'Use on DI guitar sends; adjust drive for song style.'),
            'DRV2' => self::meta('Dual Tube Drive', 'Dual tube saturation for stereo instrument warmth.', ['Horns', 'Special FX'], 'Special FX', 'medium', 'Adds harmonics to horns and keys without heavy distortion.'),
            'DRV' => self::meta('Tube Drive', 'Tube stage saturation for warmth on vocals and instruments.', ['Vocals', 'Horns', 'Special FX'], 'Special FX', 'medium', 'Gentle drive on vocal for rock and blues tones.'),
            'PIT2' => self::meta('Dual Pitch Harmonizer', 'Dual pitch shifter for stereo harmony effects.', ['Vocals', 'Special FX'], 'Modulation', 'advanced', 'Set intervals to song key; blend low for harmonies.'),
            'PIT' => self::meta('Pitch Harmonizer', 'Pitch shift and harmony generator for vocal effects.', ['Vocals', 'Special FX'], 'Modulation', 'advanced', 'Octave and harmony throws on selected phrases only.'),
        ];
    }

    /**
     * @param  array<int, string>  $recommendedFor
     * @return array{
     *     operator_name: string,
     *     operator_description: string,
     *     recommended_for_json: array<int, string>,
     *     operator_category: string,
     *     difficulty: string,
     *     starter_notes: string
     * }
     */
    private static function meta(
        string $operatorName,
        string $description,
        array $recommendedFor,
        string $category,
        string $difficulty,
        string $starterNotes,
    ): array {
        return [
            'operator_name' => $operatorName,
            'operator_description' => $description,
            'recommended_for_json' => $recommendedFor,
            'operator_category' => $category,
            'difficulty' => $difficulty,
            'starter_notes' => $starterNotes,
        ];
    }

    /**
     * @return array{
     *     operator_name: string,
     *     operator_description: string,
     *     recommended_for_json: array<int, string>,
     *     operator_category: string,
     *     difficulty: string,
     *     starter_notes: string
     * }
     */
    public static function forCode(string $code): array
    {
        $catalogue = self::metadataByCode();

        if (! isset($catalogue[$code])) {
            return self::meta(
                $code,
                'X32 effect algorithm — operator notes pending.',
                [],
                'Utility',
                'medium',
                'Refer to X32 effects documentation for parameter details.',
            );
        }

        return $catalogue[$code];
    }
}
