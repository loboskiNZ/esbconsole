const DMX = require('dmx');

class LightingEngine {
    constructor(dmxInstance, universeName = 'demo', driver = 'null', deviceId = null) {
        this.dmx = dmxInstance;
        // Register Universe with Driver and Device ID (Port)
        this.universe = this.dmx.addUniverse(universeName, driver, deviceId);
        this.currentAnimation = null;
        this.activeProgram = 'blackout';
        this.timeouts = []; // Trace active timeouts

        // --- CONSTANTS ---
        this.Colors = {
            RED: { r:255, g:0, b:0, w:0 },
            BLUE: { r:0, g:0, b:255, w:0 },
            GREEN: { r:0, g:255, b:0, w:0 },
            AMBER: { r:255, g:120, b:0, w:0 },
            CYAN: { r:0, g:255, b:255, w:0 },
            GOLD: { r:255, g:200, b:0, w:0 }, // Adjusted
            WHITE: { r:255, g:255, b:255, w:255 },
            OFF: { r:0, g:0, b:0, w:0 }
        };

        // --- PATCH ---
        // 4x Bars (48ch each) -> 1, 49, 97, 145  (occupies 1..192)
        // 4x Washes (3ch RGB each, spaced) -> 232, 236, 240, 244 (occupies 232..246, gaps at +3)
        // 3x Movers (14ch each) -> 247, 261, 275 (occupies 247..288)
        this.fixtures = {
            bars: [1, 49, 97, 145], // 4x 48CH Bars (1..192)
            movers: [247, 261, 275], // 3x 14CH Movers (247..288)
            washes: [232, 236, 240, 244] // WASHES SPACED: Stride 4 (232..247)
        };

// --- FOOTPRINTS / MAPS ---
this.BAR_FOOTPRINT = 48;          // 8 segments * 6ch
this.BAR_SEGMENTS = 8;
this.BAR_SEG_FOOTPRINT = 6;

this.WASH_FOOTPRINT = 3;          // RGB only
this.MOVER_FOOTPRINT = 14;         // 14ch movers

// Mover channel map (0-based offsets from mover base)
// (Matches how this file already uses addr+0, addr+1, addr+5, addr+6, addr+7..10)
this.MOVER = {
    PAN: 0,       // Ch 1
    // PAN_FINE: 1
    TILT: 2,      // Ch 3
    // TILT_FINE: 3
    // SPEED: 4   // Ch 5
    DIMMER: 5,    // Ch 6
    R: 6,         // Ch 7
    G: 7,         // Ch 8
    B: 8,         // Ch 9
    W: 9,         // Ch 10
    SHUTTER: 10   // Ch 11 (Strobe)
};
    }

    // --- UTILS ---
    update(channels) {
        const clean = {};
        for(const k in channels) {
            clean[k] = Math.max(0, Math.min(255, Math.floor(channels[k])));
        }
        this.universe.update(clean);
    }
    
    addTimeout(fn, delay) {
        // Hardened: safe even if setTimeout is mocked to execute synchronously.
        let timeoutId;
        timeoutId = setTimeout(() => {
            fn();
            // Remove self from list after running
            this.timeouts = this.timeouts.filter(id => id !== timeoutId);
        }, delay);
        this.timeouts.push(timeoutId);
        return timeoutId;
    }

    fillRange(start, count, val) {
        const up = {};
        for(let i=0; i<count; i++) up[start+i] = val;
        return up;
    }

    // --- SCENES / PROGRAMS ---

    // --- SCENES / PROGRAMS ---

    // EXPORTED HELPER: Set Color on Bar Segment
    // Handles the 6-channel logic: Dim, Strobe, R, G, B, Pgm
    setBarPixel(updateObj, baseAddr, r, g, b, w=0) {
        // Mode 48CH: [Dim, Strobe, R, G, B, Pgm]
        updateObj[baseAddr + 0] = 255; // Dimmer
        updateObj[baseAddr + 1] = 0;   // Strobe Open
        updateObj[baseAddr + 2] = r;   // Red
        updateObj[baseAddr + 3] = g;   // Green
        updateObj[baseAddr + 4] = b;   // Blue
        updateObj[baseAddr + 5] = 0;   // Program
    }

    // HELPER: Set Color for Washes (3CH RGB)
    // Map: Ch1=Red, Ch2=Green, Ch3=Blue
    setWashPixel(updateObj, baseAddr, r, g, b) {
        updateObj[baseAddr + 0] = r; // Red
        updateObj[baseAddr + 1] = g; // Green
        updateObj[baseAddr + 2] = b; // Blue
        // No 4th channel (White/Dimmer) in 3CH mode
    }


// --- MOVER HELPERS (14CH) ---
setMoverPosition(updateObj, baseAddr, pan, tilt) {
    if (pan !== undefined)  updateObj[baseAddr + this.MOVER.PAN] = pan;
    if (tilt !== undefined) updateObj[baseAddr + this.MOVER.TILT] = tilt;
}

    setMoverIntensity(updateObj, baseAddr, dimmer, shutter) {
        if (dimmer !== undefined)  updateObj[baseAddr + this.MOVER.DIMMER] = dimmer;
        if (shutter !== undefined) {
             // Lixada Strobe: 0=Open. Engine uses 255=Open. Map 255->0.
             updateObj[baseAddr + this.MOVER.SHUTTER] = (shutter === 255) ? 0 : shutter;
        }
    }

setMoverColor(updateObj, baseAddr, r, g, b, w = 0) {
    if (r !== undefined) updateObj[baseAddr + this.MOVER.R] = r;
    if (g !== undefined) updateObj[baseAddr + this.MOVER.G] = g;
    if (b !== undefined) updateObj[baseAddr + this.MOVER.B] = b;
    if (w !== undefined) updateObj[baseAddr + this.MOVER.W] = w;
}

setMoverAll(updateObj, baseAddr, { pan, tilt, dimmer, shutter, r, g, b, w }) {
    this.setMoverPosition(updateObj, baseAddr, pan, tilt);
    this.setMoverIntensity(updateObj, baseAddr, dimmer, shutter);
    this.setMoverColor(updateObj, baseAddr, r, g, b, w);
}

// Fire palette helper for bars (HELL scene)
randomFireColor() {
    // Brightness varies to create flicker
    let intensity = 80 + Math.floor(Math.random() * 176); // 80..255

    // Occasionally dip darker for "breathing" fire
    if (Math.random() < 0.10) {
        intensity = Math.floor(intensity * (0.25 + Math.random() * 0.35));
    }

    // Green component controls amber-ness (biased toward low, mostly red)
    let gBase = Math.floor(160 * (Math.random() ** 1.8)); // 0..160 (skew low)

    // Occasional hotter amber burst
    if (Math.random() < 0.08) {
        gBase = Math.min(160, gBase + 60);
    }

    const scale = intensity / 255;
    const r = intensity;
    const g = Math.floor(gBase * scale);
    const b = 0;

    return { r, g, b };
}

    blackout() {
        this.stopAnimation();
        this.activeProgram = 'blackout';
        console.log('💡 Scene: Fade to Black');

        const end = {};

        // Bars
        this.fixtures.bars.forEach(addr => {
            for(let seg=0; seg<8; seg++) this.setBarPixel(end, addr + (seg*6), 0, 0, 0); 
        });
        
        // Washes
        this.fixtures.washes.forEach(addr => {
            this.setWashPixel(end, addr, 0, 0, 0); 
        });

        // Movers
        this.fixtures.movers.forEach(addr => {
            this.setMoverPosition(end, addr, 127, 127);
            this.setMoverIntensity(end, addr, 0, undefined);
            this.setMoverColor(end, addr, 0, 0, 0, 0);
        });

        this.currentAnimation = new this.dmx.animation().add(end, 1000).run(this.universe);
    }



    play(sceneName) {
        try {
            this.stopAnimation();
            this.activeProgram = sceneName; 
            console.log(`💡 Scene: ${sceneName} [Switching...]`);

            switch(sceneName) {
                case 'setup': this.sceneSetup(); break;
                case 'hell': this.sceneHell(); break;
                case 'sunshine': this.sceneSunshine(); break;
                case 'madness': this.sceneMadness(); break;
                case 'aqua': this.sceneAqua(); break;
                case 'rasta': this.sceneRasta(); break;
                case 'focus': this.sceneFocus(); break;
                case 'focusLeft': this.sceneFocusLeft(); break;
                case 'focusRight': this.sceneFocusRight(); break;
                case 'police': this.scenePolice(); break;
                case 'blackout': this.blackout(); break;
                case 'test': this.sceneTest(); break;
                default: console.warn('Unknown Scene:', sceneName);
            }
        } catch (err) {
            console.error('❌ Error switching scene:', err);
        }
    }

    sceneTest() {
        this.stopAnimation();
        this.activeProgram = 'test';
        console.log('💡 Scene: TEST (Final Verification - RGB Chase)');

        // Clear All
        const clear = {};
        for(let i=1; i<=512; i++) clear[i] = 0;
        this.universe.update(clear);

        // Simple RGB Chase on Bar 1
        let step = 0;
        this.testInterval = setInterval(() => {
             if (this.activeProgram !== 'test') { clearInterval(this.testInterval); return; }
             
             const update = {};
             // Cycle RGB on Bar 1, Pixel 1
             const r = step===0 ? 255 : 0;
             const g = step===1 ? 255 : 0;
             const b = step===2 ? 255 : 0;
             
             this.setBarPixel(update, 1, r, g, b); // 1 = Bar 1 Address
             this.universe.update(update);
             
             console.log(`💡 TEST: Bar 1 Pixel 1 -> ${['RED','GREEN','BLUE'][step]}`);
             step = (step + 1) % 3;
        }, 1000); 
        
        this.testIntervalRef = this.testInterval;
    }

    toggleTestChannel(ch) {
        // Auto-enter test mode if not active
        if (this.activeProgram !== 'test') {
            this.stopAnimation();
            this.activeProgram = 'test';
            console.log('💡 Auto-Switching to Scene: TEST (Manual Override)');
            // Clear existing channels
            this.universe.updateAll(0);
            this.testChannels = {};
        }
        
        // Toggle State
        const current = this.testChannels[ch] || 0;
        const newVal = current > 0 ? 0 : 255;
        
        this.testChannels[ch] = newVal;
        
        const update = {};
        update[ch] = newVal;
        this.universe.update(update);
        console.log(`Manual Ch ${ch} -> ${newVal}`);
    }




    stopAnimation() {
        if (this.currentAnimation) {
            this.currentAnimation.stop();
            this.currentAnimation = null;
        }
        if (this.beatAnim) {
            this.beatAnim.stop();
            this.beatAnim = null;
        }
        if (this.madnessInterval) clearInterval(this.madnessInterval);
        if (this.waveInterval) clearInterval(this.waveInterval);
        if (this.policeInterval) clearInterval(this.policeInterval);
        if (this.strobeInterval) {
            clearInterval(this.strobeInterval);
            this.strobeInterval = null;
        }
        if (this.testIntervalRef) {
            clearInterval(this.testIntervalRef);
            this.testIntervalRef = null;
        }
        if (this.activeMoverAnims) {
            Object.values(this.activeMoverAnims).forEach(a => {
                if (a && typeof a.stop === 'function') a.stop();
            });
            this.activeMoverAnims = {};
        }
        if (this.extraAnimations) {
            this.extraAnimations.forEach(a => {
                if (a && typeof a.stop === 'function') a.stop();
            });
            this.extraAnimations = [];
        }
        // New: Clear Timeouts
        this.timeouts.forEach(t => clearTimeout(t));
        this.timeouts = [];
    }

    // --- BEAT SYNC ---
    pulse() {
        if (this.activeProgram === 'hell') {
            this.toggleHellColor();
        } else if (this.activeProgram === 'sunshine') {
            this.pulseSunshine();
        }
    }

toggleHellColor() {
    this.hellState = !this.hellState;

    // Keep HELL "all red": pulse between bright red and deep red
    const r = this.hellState ? 255 : 160;

    const update = {};
    this.fixtures.movers.forEach(addr => {
        this.setMoverColor(update, addr, r, 0, 0, 0);
    });
    this.universe.update(update);
}

    pulseSunshine() {
        this.sunState = !this.sunState;
        this.sunPulseCount = (this.sunPulseCount || 0) + 1;

        const update = {};
        
        // Bars: Split Red / Amber (Instant, No Fade)
        const c1 = {r:255, g:0, b:0};   // Red
        const c2 = {r:255, g:120, b:0}; // Amber
        
        this.fixtures.bars.forEach(startAddr => {
             for(let seg=0; seg<8; seg++) {
                 // Even/Odd segments swap colors
                 const isRed = this.sunState ? (seg % 2 === 0) : (seg % 2 !== 0);
                 const color = isRed ? c1 : c2;
                 this.setBarPixel(update, startAddr + (seg*6), color.r, color.g, color.b);
             }
        });

        // Washes: Double Time (Every 2nd beat)
        const washState = Math.floor(this.sunPulseCount / 2) % 2 === 0;
        const dimHigh = 0.75;
        const dimLow = 0.60;
        
        this.fixtures.washes.forEach((addr, i) => {
            const isHigh = washState ? (i % 2 === 0) : (i % 2 !== 0);
            const dim = isHigh ? dimHigh : dimLow;

            const r = Math.floor(255 * dim);     
            const g = Math.floor(200 * dim);   
            // Use Helper for RGB
            this.setWashPixel(update, addr, r, g, 0);
        });

        // Instant Update (No Animation to avoid artifacts)
        this.universe.update(update);
        
        // Clear beatAnim if it exists from old calls
        if (this.beatAnim) {
            this.beatAnim.stop();
            this.beatAnim = null;
        }
    }

    sceneSetup() {
        // All White Fade In
        this.stopAnimation();
        this.activeProgram = 'setup';
        console.log('💡 Scene: SETUP (White Fade In)');

        const init = {};   // Instant (Set RGB, Strobe Off)
        const start = {};  // Fade Start (Dimmer = 0)
        const end = {};    // Fade End (Dimmer = 255)

        // Bars: 48CH Mode (8 Segments x 6 Ch)
        this.fixtures.bars.forEach(addr => {
            for(let seg=0; seg<8; seg++) {
                const base = addr + (seg*6);
                // Init: Strobe=0, Pgm=0, RGB=255
                init[base+1] = 0;   // Strobe Open
                init[base+2] = 255; // Red
                init[base+3] = 255; // Green
                init[base+4] = 255; // Blue
                init[base+5] = 0;   // Program
                
                // Fade Dimmer (Base+0)
                start[base] = 0;
                end[base] = 255;
            }
        });

        // Washes: Fade Dimmer? Or just Fade RGB? 
        // 4CH Mode: R,G,B,W? Or Dim,R,G,B?
        // Usually 4CH is R,G,B,W (No Master Dimmer).
        // If so, we fade RGBW from 0 -> 255.
        // Wait, user said Wash is GRB.
        // Washes: Fade RGB 0->255
        this.fixtures.washes.forEach(addr => {
            // Start Black
            this.setWashPixel(start, addr, 0, 0, 0);
            // End White
            this.setWashPixel(end, addr, 255, 255, 255);
        });

        // Movers: Position Instant, Dimmer Fade
        this.fixtures.movers.forEach(addr => {
             // Instant
             init[addr + this.MOVER.PAN] = 127; // Pan Center
             init[addr + this.MOVER.TILT] = 127;   // Tilt Center (Horizontal)
             init[addr + this.MOVER.SHUTTER] = 0; // Shutter Open (Lixada: 0=Open)
             
             // White Color
             init[addr + this.MOVER.R] = 255; // R
             init[addr + this.MOVER.G] = 255; // G
             init[addr + this.MOVER.B] = 255; // B
             init[addr + this.MOVER.W] = 255;// W

             // Fade Dimmer
             start[addr + this.MOVER.DIMMER] = 0;  // Dimmer Start
             end[addr + this.MOVER.DIMMER] = 255;  // Dimmer End
        });

        // Apply Instant & Start
        this.universe.update({...init, ...start});

        // Animate
        this.currentAnimation = new this.dmx.animation()
            .add(end, 1500)
            .run(this.universe);
    }

sceneHell() {
    this.stopAnimation();
    this.activeProgram = 'hell';
    console.log('💡 Scene: HELL (Fire Red + Mover Swivel)');

    // 1) Initial State (Instant)
    const update = {};

    // Bars: initial fire look (random amber/red intensity per segment)
    this.fixtures.bars.forEach(addr => {
        for (let seg = 0; seg < 8; seg++) {
            const base = addr + (seg * 6);
            const c = this.randomFireColor();
            this.setBarPixel(update, base, c.r, c.g, c.b);
        }
    });

    // Washes: solid red
    this.fixtures.washes.forEach(addr => {
        this.setWashPixel(update, addr, 255, 0, 0);
    });

    // Movers: RED, TILT VERTICAL (10), PAN CENTER SWIVEL
    this.fixtures.movers.forEach(addr => {
        this.setMoverAll(update, addr, {
            pan: 127,
            tilt: 90, // User requested 90
            dimmer: 255,
            shutter: 255, // 255 maps to 0 (open) via setMoverIntensity
            r: 255,
            g: 0,
            b: 0,
            w: 0
        });
        // FIX: Clear channels 12/13/14 (Offsets 11-13)
        update[addr+11] = 0; 
        update[addr+12] = 0; 
        update[addr+13] = 0;
    });

    this.universe.update(update);

    // 2) Animation Loop
    // Movers: swivel side-to-side (PAN), keep tilt "up"
    // Bars: continuous fire flicker
    let tick = 0;
    const centerPan = 127;
    const panRange = 40; // FIX: 40 amplitude = 80 degree swivel width
    const tiltUp = 90;

    this.hellInterval = setInterval(() => {
        if (this.activeProgram !== 'hell') { clearInterval(this.hellInterval); return; }

        const animUpdate = {};

        // Bars: Random Independent Fire Fade (Faster)
        this.fixtures.bars.forEach((addr, i) => {
            for (let seg = 0; seg < 8; seg++) {
                const base = addr + (seg * 6);
                
                // Deterministic Randomness for Speed/Phase
                // Speed: 0.2 to 0.5 (Slightly Faster)
                const seed = (i * 8) + seg;
                const speed = 0.2 + ((seed * 13) % 30) * 0.01; 
                const phase = seed * 3.5; 
                
                const val = (Math.sin(tick * speed + phase) + 1) / 2;
                
                const r = 255;
                const g = Math.floor(val * 140); 
                
                this.setBarPixel(animUpdate, base, r, g, 0);
            }
        });

        // Movers swivel
        this.fixtures.movers.forEach((addr, i) => {
            const phase = i * 0.9;
            const pan = centerPan + Math.sin((tick * 0.06) + phase) * panRange;
            this.setMoverPosition(animUpdate, addr, pan, tiltUp);
        });

        this.universe.update(animUpdate);
        tick++;
    }, 50);

    // Re-use existing stopAnimation clearing behavior (madnessInterval is cleared there)
    this.madnessInterval = this.hellInterval;
}

    animHellWobble() {}

    sceneMadness() {
        this.stopAnimation();
        this.activeProgram = 'madness';
        console.log('💡 Scene: MADNESS');
        
        // Internal Strobe (No Clock Required)
        this.madnessInterval = setInterval(() => this.pulseMadness(), 100);
        this.pulseMadness();
    }

    pulseMadness() {
        if (this.activeProgram !== 'madness') return;
        
        const update = {};
        const pixels = [];
        
        // 1. Gather Pool (8 Segments per bar)
        this.fixtures.bars.forEach(addr => {
            for(let i=0; i<8; i++) pixels.push({ type: 'dsrgbp', addr: addr + (i*6) });
        });
        this.fixtures.washes.forEach(addr => pixels.push({ type: 'rgbw', addr }));
        this.fixtures.movers.forEach(addr => pixels.push({ type: 'mover', addr }));
        
        // 2. Default: All OFF (Using Helpers)
        this.fixtures.bars.forEach(addr => { 
            for(let i=0; i<8; i++) this.setBarPixel(update, addr+(i*6), 0,0,0);
        });
        this.fixtures.washes.forEach(addr => { 
            this.setWashPixel(update, addr, 0, 0, 0);
        });
        this.fixtures.movers.forEach(addr => { 
            // Keep Position (Don't reset)
            this.setMoverIntensity(update, addr, 0, 255);
            this.setMoverColor(update, addr, 0, 0, 0, 0);
        });

        // 3. Select Random Winners (Min 3, Max 12)
        const count = Math.floor(Math.random() * 10) + 3; 
        for(let k=0; k<count; k++) {
            const idx = Math.floor(Math.random() * pixels.length);
            const p = pixels[idx];
            
            // Turn ON (White)
            if (p.type === 'dsrgbp') {
                this.setBarPixel(update, p.addr, 255, 255, 255);
            } else if (p.type === 'rgbw') {
                this.setWashPixel(update, p.addr, 255, 255, 255);
            } else if (p.type === 'mover') {
                // Random Position
                update[p.addr + this.MOVER.PAN] = Math.floor(Math.random() * 256);   // Offset 0: Pan (Full Random)
                update[p.addr + this.MOVER.TILT] = Math.floor(Math.random() * 41) + 80; // Offset 2: Tilt (Fast 80-120)
                
                update[p.addr + 5] = 255; // Offset 5: Dimmer
                
                update[p.addr + 6] = 255; // Offset 6: Red
                update[p.addr + 7] = 255; // Offset 7: Green
                update[p.addr + 8] = 255; // Offset 8: Blue
                update[p.addr + 9] = 255; // Offset 9: White
                update[p.addr + 10]= 0;   // Offset 10: Strobe (0 = Open)
            }
        }

        this.universe.update(update);
    }

    sceneSunshine() {
        this.stopAnimation();
        this.activeProgram = 'sunshine';
        console.log('💡 Scene: SUNSHINE');

        this.sunPulseCount = 0; // Init Beat Counter

        const update = {};

        // Movers: Yellow (R255 G255) + White (50)
        // Position Init: Top of Circle (Pan 127, Tilt 80)
        this.fixtures.movers.forEach(addr => {
            this.setMoverAll(update, addr, {
                pan: 127, tilt: 80,
                dimmer: 255, shutter: 255,
                r: 255, g: 255, b: 0, w: 50
            });
        });

        this.universe.update(update);
        
        // Initial Pulse Call to set Front/Wash
        this.sunState = false;
        this.pulseSunshine();

        // Mover Animation (Circle)
        this.animSunshineCircle();
    }

    animSunshineCircle() {
        // Circle Points: Top, Right, Bottom, Left
        const p1 = {}; const p2 = {}; const p3 = {}; const p4 = {};
        
        // Offset 9 = White. Dim between 0 and 127.
        // We will cycle White: 0 -> 127 -> 0 -> 127
        this.fixtures.movers.forEach(addr => {
             // Top
             // Note: Original P1 keys were addr+0 (Pan) and addr+1 (Tilt?? NO!)
             // Wait, previous code used +0 and +1. But +1 is UNDEFINED in new map. +2 is Tilt.
             // FIX: Use correct offsets: +0 (Pan), +2 (Tilt), +9 (White)
             
             // Top (White 0)
             p1[addr+this.MOVER.PAN] = 127; 
             p1[addr+this.MOVER.TILT] = 80;
             p1[addr+this.MOVER.W] = 0; 

             // Right (White 64 - rising)
             p2[addr+this.MOVER.PAN] = 147; 
             p2[addr+this.MOVER.TILT] = 100;
             p2[addr+this.MOVER.W] = 64;

             // Bottom (White 127 - max)
             p3[addr+this.MOVER.PAN] = 127; 
             p3[addr+this.MOVER.TILT] = 120;
             p3[addr+this.MOVER.W] = 127;

             // Left (White 64 - falling)
             p4[addr+this.MOVER.PAN] = 107; 
             p4[addr+this.MOVER.TILT] = 100;
             p4[addr+this.MOVER.W] = 64;
        });

        const loop = () => {
             if (this.activeProgram !== 'sunshine') return;
             this.currentAnimation = new this.dmx.animation()
                .add(p2, 2000).add(p3, 2000).add(p4, 2000).add(p1, 2000) // Smooth (2s per segment)
                .run(this.universe, loop);
        };
        loop();
    }

    sceneAqua() {
        this.stopAnimation();
        this.activeProgram = 'aqua';
        console.log('💡 Scene: AQUA (Cyan Random Fade)');

        let tick = 0;

        // Movers Initialization: Pan/Tilt Center, Cyan (G+B), Dimmer Full
        const init = {};
        this.fixtures.movers.forEach(addr => {
             this.setMoverAll(init, addr, {
                 pan: 127, tilt: 127,
                 dimmer: 255, shutter: 0,
                 r: 0, g: 255, b: 255, w: 0
             });
        });
        this.universe.update(init);

        this.waveInterval = setInterval(() => {
            if (this.activeProgram !== 'aqua') { clearInterval(this.waveInterval); return; }

            const update = {};
            tick++;

            // Bars: Cyan Pulse (Green & Blue 127-255)
            // Independent Random Speed 0.5 - 0.7
            this.fixtures.bars.forEach((addr, i) => {
                for(let seg=0; seg<8; seg++) {
                     const base = addr + (seg*6);
                     const seed = (i*8)+seg;
                     // Speed 0.5 to 0.7
                     const speed = 0.5 + ((seed * 7) % 20) * 0.01; 
                     const phase = seed * 2.0;

                     // Sine 0..1
                     const sine = (Math.sin(tick * speed + phase) + 1) / 2;
                     const val = 127 + Math.floor(sine * 128); // 127..255
                     
                     this.setBarPixel(update, base, 0, val, val);
                }
            });

            // Movers: Fast White Dimming (0-50)
            this.fixtures.movers.forEach(addr => {
                // Random 0-50
                update[addr + this.MOVER.W] = Math.floor(Math.random() * 51);
            });

            this.universe.update(update);

        }, 50);
    }

    sceneRasta() {
        this.stopAnimation();
        this.activeProgram = 'rasta';
        console.log('💡 Scene: RASTA (Refactored)');

        const update = {};

        // 1. MOVERS (Static Fixed)
        // 0=125, 1=0, 2=127, 3=0, 4=0, 5=255, 6=255, 7=0, 8=0, 9=0, 10=0
        this.fixtures.movers.forEach(addr => {
            update[addr + this.MOVER.PAN]    = 125;
            update[addr + 1]                 = 0;
            update[addr + this.MOVER.TILT]   = 127;
            update[addr + 3]                 = 0;
            update[addr + 4]                 = 0;
            update[addr + this.MOVER.DIMMER] = 255;
            update[addr + this.MOVER.R]      = 255;
            update[addr + this.MOVER.G]      = 0;
            update[addr + this.MOVER.B]      = 0;
            update[addr + this.MOVER.W]      = 0;
            update[addr + this.MOVER.SHUTTER]= 0;
        });

        // 2. Prepare Organic Wave (Bars & Washes)
        this.wavePixels = [];
        const speedBase = 0.001; const speedVar = 0.002;

        // Bars: Green Base
        this.fixtures.bars.forEach(addr => {
            for(let i=0; i<8; i++) {
                this.wavePixels.push({
                    type: 'rgb', addr: addr + (i*6),
                    phase: Math.random() * Math.PI * 2,
                    speed: speedBase + (Math.random() * speedVar),
                    r: 0, g: 1, b: 0 // Base Color
                });
            }
        });
        // Washes: Gold Base
        this.fixtures.washes.forEach(addr => {
            this.wavePixels.push({
                type: 'rgbw', addr: addr,
                phase: Math.random() * Math.PI * 2,
                speed: speedBase + (Math.random() * speedVar),
                r: 1, g: 0.8, b: 0
            });
        });

        this.universe.update(update);

        // 3. Start Animation
        this.animOrganicWave();
        
        // 4. Splashes (Red) - Controlled by animRandomSplashes
        // Note: animRandomSplashes logic has been patched to skip movers if program is 'rasta'
        this.animRandomSplashes('rasta', {r:255, g:0, b:0, w:0});
    }

    animOrganicWave() {
        this.waveInterval = setInterval(() => {
            if (this.activeProgram !== 'rasta') { clearInterval(this.waveInterval); return; }

            const now = Date.now();
            const update = {};
            
            this.wavePixels.forEach(p => {
                // Sine Wave Logic
                const phase = p.phase + (now * p.speed * 0.001); 
                const sine = Math.sin(phase);
                const val = (sine + 1) / 2;
                const intensity = Math.floor(val * 255);
                
                if (p.type === 'rgb') {
                     this.setBarPixel(update, p.addr, p.r*intensity, p.g*intensity, p.b*intensity);
                } else if (p.type === 'rgbw') {
                     this.setWashPixel(update, p.addr, p.r*intensity, p.g*intensity, p.b*intensity);
                }
            });

            // Movers: Pan Oscillate 117 <-> 137
            const panOsc = 127 + Math.sin(now * 0.002) * 10; 
            // Tilt Oscillate 117 <-> 147 (Center 132, Amp 15)
            const tiltOsc = 132 + Math.sin(now * 0.0015) * 15; 

            this.fixtures.movers.forEach(addr => {
                update[addr + this.MOVER.PAN] = Math.floor(panOsc);
                update[addr + this.MOVER.TILT] = Math.floor(tiltOsc);
            });

            this.universe.update(update);
        }, 50);
    }

    animRandomSplashes(programName, color) {
        // If Rasta, we want fixed movers (controlled in animOrganicWave), so skip splashes for movers
        if (programName === 'rasta') return;

        this.activeMoverAnims = {}; 
        this.fixtures.movers.forEach((addr, i) => {
             this.runSplashCycle(addr, i * 1500, programName, color); 
        });
    }

    runSplashCycle(addr, delay, programName, color) {
         if (this.activeProgram !== programName) return;

         this.addTimeout(() => {
             if (this.activeProgram !== programName) return;
             
             // 1. Move Dark
             const pan = Math.floor(Math.random() * 256);
             const tilt = Math.floor(Math.random() * 86);
             
             const setup = {};
             setup[addr] = pan; 
             setup[addr+1] = tilt;
             setup[addr+5] = 0; 
             this.universe.update(setup);

             // 2. Animate
             const fadeIn = {}; 
             fadeIn[addr+5] = 255; 
             fadeIn[addr+7] = color.r;
             fadeIn[addr+8] = color.g;
             fadeIn[addr+9] = color.b;
             fadeIn[addr+10]= color.w;
             
             const fadeOut = {};
             fadeOut[addr+5] = 0;

             const anim = new this.dmx.animation()
                .add(fadeIn, 2000)
                .delay(500)
                .add(fadeOut, 2000)
                .run(this.universe, () => {
                     this.runSplashCycle(addr, Math.random() * 2000, programName, color); 
                });
             
             // Track Active Animation
             if (this.activeMoverAnims) {
                 this.activeMoverAnims[addr] = anim;
             }

         }, delay);
    }

    sceneFocusLeft() {
        this.stopAnimation();
        this.activeProgram = 'focusLeft';
        console.log('💡 Scene: FOCUS LEFT (Mover 1 White Spot)');
        this.applyStrictFocus(0, 0); // Active: 0, Pan: 0
    }

    sceneFocus() {
        this.stopAnimation();
        this.activeProgram = 'focus';
        console.log('💡 Scene: FOCUS CENTER (Mover 2 White Spot)');
        this.applyStrictFocus(1, 0); // Active: 1, Pan: 0
    }

    sceneFocusRight() {
        this.stopAnimation();
        this.activeProgram = 'focusRight';
        console.log('💡 Scene: FOCUS RIGHT (Mover 3 White Spot)');
        this.applyStrictFocus(2, 0); // Active: 2, Pan: 0
    }

    applyStrictFocus(activeIndex, panValue = 127) {
        const update = {};

        // 1. Blackout Bars & Washes (Clear stage)
        this.fixtures.bars.forEach(addr => { for(let i=0; i<8; i++) this.setBarPixel(update, addr+(i*6), 0,0,0); });
        this.fixtures.washes.forEach(addr => { this.setWashPixel(update, addr, 0,0,0); });

        // 2. Set Movers
        this.fixtures.movers.forEach((addr, i) => {
            if (i === activeIndex) {
                // Active Mover: White Spot (Pan defined, Tilt 75)
                update[addr + this.MOVER.PAN] = panValue; // 0: Pan
                update[addr + 1]              = 0;        // 1: Pan Fine
                update[addr + this.MOVER.TILT] = 75;      // 2: Tilt
                update[addr + 3]              = 0;        // 3: Tilt Fine
                update[addr + 4]              = 0;        // 4: Speed
                update[addr + this.MOVER.DIMMER] = 255;   // 5: Dimmer
                update[addr + this.MOVER.R]   = 0;        // 6: Red
                update[addr + this.MOVER.G]   = 0;        // 7: Green
                update[addr + this.MOVER.B]   = 0;        // 8: Blue
                update[addr + this.MOVER.W]   = 255;      // 9: White
                update[addr + this.MOVER.SHUTTER] = 0;    // 10: Strobe
            } else {
                // Others: Blackout
                update[addr + this.MOVER.DIMMER] = 0;
                update[addr + this.MOVER.R] = 0;
                update[addr + this.MOVER.G] = 0;
                update[addr + this.MOVER.B] = 0;
                update[addr + this.MOVER.W] = 0;
            }
        });

        this.universe.update(update);
    }

    scenePolice() {
        this.stopAnimation();
        this.activeProgram = 'police';
        console.log('💡 Scene: POLICE 🚨 (Refactored Sweep)');

        let state = false;
        
        // Bars/Washes Loop (Simple Blink)
        // Movers Loop (Sweep Crossfade)
        this.policeInterval = setInterval(() => {
            if (this.activeProgram !== 'police') { clearInterval(this.policeInterval); return; }

            const now = Date.now();
            const update = {};
            
            // --- MOVERS ---
            // Oscillation: 64 to 192. Center 128, Amp 64.
            // Speed: 0.002 (Synchronized Speed) + Phase Offset (Desync)
            this.fixtures.movers.forEach((addr, i) => {
                 const phase = (now * 0.002) + (i * 2.0); // Offset by ~1/3 cycle
                 const sine = Math.sin(phase);
                 const pan = 128 + (sine * 64);
                 
                 // Switch Logic (Hard Cut)
                 const isLeft = pan < 128;
                 const valRed = isLeft ? 255 : 0;
                 const valBlue = isLeft ? 0 : 255;

                 update[addr + this.MOVER.PAN]     = Math.floor(pan); // Offset 0
                 update[addr + 1]                  = 0;
                 update[addr + this.MOVER.TILT]    = 0;               // Offset 2
                 update[addr + 3]                  = 0;
                 update[addr + 4]                  = 0;
                 update[addr + this.MOVER.DIMMER]  = 255;             // Offset 5
                 update[addr + this.MOVER.SHUTTER] = 0;               // Offset 6
                 update[addr + this.MOVER.R]       = valRed;          // Offset 7
                 update[addr + this.MOVER.G]       = 0;               // Offset 8
                 update[addr + this.MOVER.B]       = valBlue;         // Offset 9
                 update[addr + this.MOVER.W]       = 0;               // Offset 10
            });


            // --- BARS & WASHES (Contextual Background) ---
            // Sync with sweep? Or simple blink? 
            // Simple blink state logic (every ~150ms handled by modulo)
            state = Math.floor(now / 150) % 2 === 0;
            const c1 = state ? {r:255, g:0, b:0} : {r:0, g:0, b:255};
            const c2 = state ? {r:0, g:0, b:255} : {r:255, g:0, b:0};

            this.fixtures.bars.forEach(addr => {
                for(let i=0; i<8; i++) {
                     const c = (i%2===0) ? c1 : c2;
                     this.setBarPixel(update, addr+(i*6), c.r, c.g, c.b);
                }
            });
            this.fixtures.washes.forEach((addr, i) => {
                 const c = (i%2===0) ? c1 : c2;
                 this.setWashPixel(update, addr, c.r, c.g, c.b);
            });

            this.universe.update(update);

        }, 50); // 20fps for smooth sweep
    }


}

module.exports = LightingEngine;
