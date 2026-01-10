export const INSTRUMENT_PRESETS = {
    'Kick (Out)': {
        hpf: false, // Let the low end breathe
        eq: {
            1: { type: 2, f: 0.20, g: 0.65, q: 0.4 }, // Low Shelf Boost ~60Hz
            2: { type: 0, f: 0.55, g: 0.35, q: 0.6 }, // Cut ~400Hz (Mud) - mapped 0-1
            3: { type: 0, f: 0.82, g: 0.60, q: 0.5 }, // Boost ~4kHz (Click)
            4: { type: 5, f: 0.95, g: 0.5, q: 0.5 }   // HCut (optional)
        },
        gate: { on: true, thr: 0.35, attack: 0.05, hold: 0.1, release: 0.2 },
        dyn: { on: true, thr: 0.4, ratio: 0.15, attack: 0.05, release: 0.3 } // 4:1 Ratio approx
    },
    'Snare': {
        hpf: true, hpfFreq: 0.4, // ~80-100Hz
        eq: {
            1: { type: 1, f: 0.45, g: 0.60, q: 0.5 }, // Body Boost ~200Hz
            2: { type: 0, f: 0.60, g: 0.40, q: 0.7 }, // Cut Boxiness
            3: { type: 0, f: 0.85, g: 0.65, q: 0.5 }, // Crack ~5kHz
            4: { type: 4, f: 0.95, g: 0.6, q: 0.5 }   // Air
        },
        gate: { on: true, thr: 0.3, attack: 0.0, hold: 0.05, release: 0.15 },
        dyn: { on: true, thr: 0.35, ratio: 0.15, attack: 0.0, release: 0.2 }
    },
    'Rack Tom': {
        hpf: true, hpfFreq: 0.45, // ~100Hz
        eq: {
            1: { type: 2, f: 0.40, g: 0.6, q: 0.5 }, // Bottom
            2: { type: 0, f: 0.60, g: 0.3, q: 0.8 }, // Cut Ring ~500Hz
            3: { type: 0, f: 0.80, g: 0.6, q: 0.5 }, // Attack ~3kHz
            4: { type: 4, f: 0.9, g: 0.5, q: 0.5 }
        },
        gate: { on: true, thr: 0.4, attack: 0.05, hold: 0.1, release: 0.3 },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Floor Tom': {
        hpf: true, hpfFreq: 0.35, // ~60Hz
        eq: {
            1: { type: 2, f: 0.35, g: 0.65, q: 0.5 }, // Boom
            2: { type: 0, f: 0.55, g: 0.3, q: 0.8 }, // Cut Boxiness
            3: { type: 0, f: 0.78, g: 0.6, q: 0.5 }, // Attack
            4: { type: 4, f: 0.9, g: 0.5, q: 0.5 }
        },
        gate: { on: true, thr: 0.4, attack: 0.05, hold: 0.1, release: 0.4 },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Overheads': {
        hpf: true, hpfFreq: 0.65, // ~400Hz (Cymbals only)
        eq: {
            1: { type: 1, f: 0.5, g: 0.5, q: 0.5 }, // Flat
            2: { type: 0, f: 0.7, g: 0.45, q: 0.5 }, // Mild Cut
            3: { type: 0, f: 0.9, g: 0.6, q: 0.5 }, // Shimmer
            4: { type: 4, f: 0.98, g: 0.65, q: 0.5 } // Air
        },
        gate: { on: false },
        dyn: { on: true, thr: 0.6, ratio: 0.1, attack: 0.2, release: 0.5 }
    },
    'Bass Gtr': {
        hpf: true, hpfFreq: 0.3, // ~40Hz
        eq: {
            1: { type: 2, f: 0.35, g: 0.6, q: 0.5 }, // Lows
            2: { type: 0, f: 0.55, g: 0.4, q: 0.6 }, // Cut Mud ~300Hz
            3: { type: 0, f: 0.70, g: 0.55, q: 0.5 }, // Definition ~800Hz
            4: { type: 5, f: 0.9, g: 0.5, q: 0.5 }   // HCut noise
        },
        dyn: { on: true, thr: 0.3, ratio: 0.2, attack: 0.2, release: 0.4 } // Compression is key
    },
    'E. Guitar': {
        hpf: true, hpfFreq: 0.45, // ~100Hz
        eq: {
            1: { type: 1, f: 0.45, g: 0.5, q: 0.5 }, 
            2: { type: 0, f: 0.60, g: 0.45, q: 0.5 }, // Cut Mud
            3: { type: 0, f: 0.78, g: 0.6, q: 0.6 }, // Bite ~2.5kHz
            4: { type: 5, f: 0.9, g: 0.5, q: 0.5 }   // HCut Fizz
        },
        dyn: { on: true, thr: 0.5, ratio: 0.1, attack: 0.1, release: 0.3 }
    },
    'Ac. Guitar': {
        hpf: true, hpfFreq: 0.5, // ~120Hz
        eq: {
            1: { type: 1, f: 0.5, g: 0.45, q: 0.5 }, // Cut Body Boom
            2: { type: 0, f: 0.65, g: 0.4, q: 0.7 }, // Cut Feedback range
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 }, // Presence
            4: { type: 4, f: 0.95, g: 0.65, q: 0.5 } // Sparkle
        },
        dyn: { on: true, thr: 0.45, ratio: 0.15, attack: 0.1, release: 0.4 }
    },
    'Ukelele': {
        hpf: true, hpfFreq: 0.55, // ~200Hz
        eq: {
            1: { type: 1, f: 0.55, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.7, g: 0.4, q: 0.6 }, // Plastic sound cut
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 },
            4: { type: 4, f: 0.95, g: 0.6, q: 0.5 }
        },
        dyn: { on: true, thr: 0.5, ratio: 0.1, attack: 0.1, release: 0.3 }
    },
    'Male Vox': {
        hpf: true, hpfFreq: 0.45, // ~100Hz
        eq: {
            1: { type: 1, f: 0.45, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.58, g: 0.4, q: 0.6 }, // Cut Mud ~250-350Hz
            3: { type: 0, f: 0.82, g: 0.6, q: 0.5 }, // Presence ~3kHz
            4: { type: 4, f: 0.95, g: 0.55, q: 0.5 } // Air
        },
        dyn: { on: true, thr: 0.4, ratio: 0.15, attack: 0.1, release: 0.4 }
    },
    'Female Vox': {
        hpf: true, hpfFreq: 0.5, // ~150Hz
        eq: {
            1: { type: 1, f: 0.5, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.6, g: 0.45, q: 0.6 }, // Warmth/Mud balance
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 }, // Presence ~4kHz
            4: { type: 4, f: 0.96, g: 0.6, q: 0.5 } // Air
        },
        dyn: { on: true, thr: 0.4, ratio: 0.15, attack: 0.1, release: 0.4 }
    },
    'Trumpet': {
        hpf: true, hpfFreq: 0.5, // ~150Hz
        eq: {
            1: { type: 1, f: 0.5, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.65, g: 0.45, q: 0.6 }, // Honk cut
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 }, // Brightness
            4: { type: 4, f: 0.95, g: 0.6, q: 0.5 }
        },
        dyn: { on: true, thr: 0.3, ratio: 0.3, attack: 0.05, release: 0.2 } // Fast limiter-ish
    },
    'Alto Sax': {
        hpf: true, hpfFreq: 0.5, // ~150Hz
        eq: {
            1: { type: 1, f: 0.5, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.65, g: 0.4, q: 0.6 }, // Squawk cut
            3: { type: 0, f: 0.8, g: 0.6, q: 0.5 }, // Reed
            4: { type: 4, f: 0.95, g: 0.5, q: 0.5 }
        },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Tenor Sax': {
        hpf: true, hpfFreq: 0.45, // ~120Hz
        eq: {
            1: { type: 1, f: 0.45, g: 0.55, q: 0.5 }, // Body
            2: { type: 0, f: 0.6, g: 0.4, q: 0.6 }, // Honk
            3: { type: 0, f: 0.8, g: 0.6, q: 0.5 },
            4: { type: 4, f: 0.95, g: 0.5, q: 0.5 }
        },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Trombone': {
        hpf: true, hpfFreq: 0.4, // ~80Hz
        eq: {
            1: { type: 1, f: 0.4, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.55, g: 0.4, q: 0.6 }, // Mud
            3: { type: 0, f: 0.75, g: 0.6, q: 0.5 }, // Bite
            4: { type: 5, f: 0.9, g: 0.5, q: 0.5 }   // HCut
        },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Sousaphone': {
        hpf: true, hpfFreq: 0.25, // ~30Hz
        eq: {
            1: { type: 2, f: 0.35, g: 0.65, q: 0.5 }, // Low boost
            2: { type: 0, f: 0.5, g: 0.4, q: 0.6 }, // Mud
            3: { type: 0, f: 0.7, g: 0.55, q: 0.5 }, // Definition
            4: { type: 5, f: 0.85, g: 0.5, q: 0.5 } // HCut
        },
        dyn: { on: true, thr: 0.25, ratio: 0.4, attack: 0.05, release: 0.4 } // Heavy compression
    },
    'Conga': {
        hpf: true, hpfFreq: 0.5,
        eq: {
            1: { type: 1, f: 0.5, g: 0.55, q: 0.5 }, // Tone
            2: { type: 0, f: 0.65, g: 0.4, q: 0.8 }, // Ring
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 }, // Slap
            4: { type: 4, f: 0.95, g: 0.5, q: 0.5 }
        },
        dyn: { on: true, thr: 0.4, ratio: 0.15, attack: 0.05, release: 0.2 }
    },
    'Cowbell': {
        hpf: true, hpfFreq: 0.6,
        eq: {
            1: { type: 1, f: 0.6, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.7, g: 0.3, q: 0.8 }, // Clank
            3: { type: 0, f: 0.8, g: 0.6, q: 0.5 }, // Attack
            4: { type: 5, f: 0.95, g: 0.5, q: 0.5 }
        }
    }
};
