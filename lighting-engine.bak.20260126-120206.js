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
    PAN: 0,
    TILT: 1,
    DIMMER: 5,
    SHUTTER: 6,
    R: 7,
    G: 8,
    B: 9,
    W: 10
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
    if (shutter !== undefined) updateObj[baseAddr + this.MOVER.SHUTTER] = shutter;
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
        const color = this.hellState ? this.Colors.RED : this.Colors.AMBER;
        
        const update = {};
        this.fixtures.movers.forEach(addr => {
            update[addr+7] = color.r;
            update[addr+8] = color.g;
            update[addr+9] = color.b;
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
             init[addr + 0] = 127; // Pan Center
             init[addr + 1] = 0;   // Tilt Up
             init[addr + 6] = 255; // Shutter Open
             
             // White Color
             init[addr + 7] = 255; // R
             init[addr + 8] = 255; // G
             init[addr + 9] = 255; // B
             init[addr + 10] = 255;// W

             // Fade Dimmer
             start[addr + 5] = 0;  // Dimmer Start
             end[addr + 5] = 255;  // Dimmer End
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
        console.log('💡 Scene: HELL (Manual Interval - Force Update)');

        // 1. Initial State (Instant)
        const update = {};
        
        // Bars: Static RED (Base+2)
        this.fixtures.bars.forEach(addr => {
            for(let seg=0; seg<8; seg++) {
                // Correct Map: 1=Dim, 2=Str, 3=Red..
                // Base = addr + (seg*6)
                // Red = Base + 2
                const base = addr + (seg*6);
                update[base+0] = 255; // Dimmer
                update[base+1] = 0;   // Strobe
                update[base+2] = 255; // Red
                update[base+3] = 0;   // Green
                update[base+4] = 0;   // Blue
                update[base+5] = 0;   // Pgm
            }
        });

        // Washes: Amber (R255, G120, B0)
        this.fixtures.washes.forEach(addr => {
            this.setWashPixel(update, addr, 255, 120, 0);
        });

        // Movers: Init Red, Center
        this.fixtures.movers.forEach(addr => {
            this.setMoverAll(update, addr, {
                pan: 127, tilt: 127,
                dimmer: 255, shutter: 255,
                r: 255, g: 0, b: 0, w: 0
            });
        });
        
        this.universe.update(update);

        // 2. Animation Loop (Wobble Movers)
        let tick = 0;
        this.hellInterval = setInterval(() => {
             if(this.activeProgram !== 'hell') { clearInterval(this.hellInterval); return; }
             
             const animUpdate = {};
             const tiltOffset = Math.sin(tick * 0.1) * 10; 
             
             this.fixtures.movers.forEach(addr => {
                 animUpdate[addr+1] = 64 + tiltOffset; 
             });
             
             this.universe.update(animUpdate);
             tick++;
        }, 50);
        
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
                update[p.addr] = Math.floor(Math.random() * 256);   // Pan
                update[p.addr+1] = Math.floor(Math.random() * 256); // Tilt
                update[p.addr+5] = 255; // Dimmer
                update[p.addr+7] = 255; // R
                update[p.addr+8] = 255; // G
                update[p.addr+9] = 255; // B
                update[p.addr+10]= 255; // W
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
        
        this.fixtures.movers.forEach(addr => {
             // Top
             p1[addr+0] = 127; p1[addr+1] = 80;
             // Right
             p2[addr+0] = 147; p2[addr+1] = 100;
             // Bottom
             p3[addr+0] = 127; p3[addr+1] = 120;
             // Left
             p4[addr+0] = 107; p4[addr+1] = 100;
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

        // Deterministic PRNG (Mulberry32)
        let seed = 1337; 
        const random = () => {
            let t = seed += 0x6D2B79F5;
            t = Math.imul(t ^ (t >>> 15), t | 1);
            t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
            return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };

        // Initialize State for 32 Cells (4 Fixtures * 8 Cells)
        const cells = [];
        const now = Date.now();
        // Use live fixtures config, but ensure it's the fixed list: [1, 49, 97, 145]
        const fixStarts = this.fixtures.bars;
        
        fixStarts.forEach((startAddr, fIndex) => {
            for (let c = 0; c < 8; c++) {
                const startIntensity = 128 + Math.floor(random() * 128); // 128..255
                cells.push({
                    startAddr, c,
                    val: startIntensity,
                    startVal: startIntensity,
                    target: 128 + Math.floor(random() * 128),
                    startTime: now,
                    duration: 1500 + (random() * 3000) // 1.5s - 4.5s
                });
            }
        });

        // Animation Loop
        this.waveInterval = setInterval(() => {
            if (this.activeProgram !== 'aqua') { clearInterval(this.waveInterval); return; }

            const t = Date.now();
            const update = {};

            cells.forEach(cell => {
                // Check transition
                const elapsed = t - cell.startTime;
                
                if (elapsed >= cell.duration) {
                    // New Target
                    cell.startVal = cell.target;
                    cell.target = 128 + Math.floor(random() * 128);
                    cell.startTime = t;
                    cell.duration = 1500 + (random() * 3000);
                    cell.val = cell.startVal;
                } else {
                    // Smooth Interpolate (Smoothstep)
                    const p = elapsed / cell.duration;
                    const ease = p * p * (3 - 2 * p);
                    cell.val = cell.startVal + (cell.target - cell.startVal) * ease;
                }

                const intensity = Math.floor(Math.max(0, Math.min(255, cell.val)));

                // Address Calculation: fixtureStart + (c * 6)
                const baseAddr = cell.startAddr + (cell.c * 6);
                
                // Write DMX using setBarPixel logic inline for speed or clarity, BUT we must follow the rules:
                // Red=0, Green=I, Blue=I.
                update[baseAddr + 0] = 255;       // Dimmer
                update[baseAddr + 1] = 0;         // Strobe
                update[baseAddr + 2] = 0;         // Red
                update[baseAddr + 3] = intensity; // Green
                update[baseAddr + 4] = intensity; // Blue
                update[baseAddr + 5] = 0;         // Program
            });

            this.universe.update(update);

        }, 50);
    }

    sceneRasta() {
        this.stopAnimation();
        this.activeProgram = 'rasta';
        console.log('💡 Scene: RASTA');

        this.wavePixels = [];
        const speedBase = 0.001; const speedVar = 0.002;

        // Bars: Green (R0 G1 B0)
        this.fixtures.bars.forEach(addr => {
            for(let i=0; i<8; i++) {
                this.wavePixels.push({
                    type: 'rgb', addr: addr + (i*6),
                    phase: Math.random() * Math.PI * 2,
                    speed: speedBase + (Math.random() * speedVar),
                    r: 0, g: 1, b: 0 
                });
            }
        });
        // Washes: Gold (R1, G0.8, B0)
        this.fixtures.washes.forEach(addr => {
            this.wavePixels.push({
                type: 'rgbw', addr: addr,
                phase: Math.random() * Math.PI * 2,
                speed: speedBase + (Math.random() * speedVar),
                r: 1, g: 0.8, b: 0
            });
        });

        this.animOrganicWave();
        // Splashes: Red (R255, G0, B0)
        this.animRandomSplashes('rasta', {r:255, g:0, b:0, w:0});
    }

    animOrganicWave() {
        // Physics Loop (50ms ~ 20fps)
        this.waveInterval = setInterval(() => {
            const now = Date.now();
            const update = {};
            
            this.wavePixels.forEach(p => {
                const val = Math.sin(now * p.speed + p.phase); 
                const rawBright = 135 + (val * 85); 
                
                const r = Math.floor(rawBright * p.r);
                const g = Math.floor(rawBright * p.g);
                const b = Math.floor(rawBright * p.b);
                
                if (p.type === 'rgb') {
                    // Use Helper to enforce Dimmer/Strobe/Map
                    this.setBarPixel(update, p.addr, r, g, b);
                } else if (p.type === 'rgbw') {
                    // Use Helper for RGB
                    this.setWashPixel(update, p.addr, r, g, b); 
                }
            });
            
            this.universe.update(update);
        }, 50);
    }

    animRandomSplashes(programName, color) {
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

    sceneFocus() {
        this.stopAnimation();
        this.activeProgram = 'focus';
        console.log('💡 Scene: FOCUS CENTER [Switching...]');
        // Center: Bars 1&2 (Indices 1 & 2), Washes 1&2 (Indices 1 & 2), Mover 2 (Index 1)
        this.applyFocus('focus', [1, 2], [1, 2], 1, 127); 
    }

    sceneFocusLeft() {
        this.stopAnimation();
        this.activeProgram = 'focusLeft';
        console.log('💡 Scene: FOCUS LEFT [Switching...]');
        // Left: Bars 0&1, Washes 0&1, Mover 1 (Index 0)
        this.applyFocus('focusLeft', [0, 1], [0, 1], 0, 127);
    }

    sceneFocusRight() {
        this.stopAnimation();
        this.activeProgram = 'focusRight';
        console.log('💡 Scene: FOCUS RIGHT [Switching...]');
        // Right: Bars 2&3, Washes 2&3, Mover 3 (Index 2)
        this.applyFocus('focusRight', [2, 3], [2, 3], 2, 127);
    }

    applyFocus(sceneID, barIndices, washIndices, moverIndex, panCenter) {
        const update = {};
        const intensity = 180;

        // Reset All
        this.fixtures.bars.forEach(addr => { for(let i=0; i<8; i++) this.setBarPixel(update, addr+(i*6), 0,0,0); });
        this.fixtures.washes.forEach(addr => { this.setWashPixel(update, addr, 0,0,0); });
        this.fixtures.movers.forEach(addr => {
            this.setMoverPosition(update, addr, 127, 127);
            this.setMoverIntensity(update, addr, 0, 255);
            this.setMoverColor(update, addr, 0, 0, 0, 0);
        });

        // Bars
        if (barIndices[0] !== undefined) {
            const bar = this.fixtures.bars[barIndices[0]];
            for(let i=4; i<8; i++) {
                 const base = bar + (i*6);
                 this.setBarPixel(update, base, intensity, intensity, intensity);
            }
        }
        if (barIndices[1] !== undefined) {
             const bar = this.fixtures.bars[barIndices[1]];
             for(let i=0; i<4; i++) {
                 const base = bar + (i*6);
                 this.setBarPixel(update, base, intensity, intensity, intensity);
            }
        }

        // Washes
        washIndices.forEach(idx => {
            const addr = this.fixtures.washes[idx];
            if (addr) {
                this.setWashPixel(update, addr, intensity, intensity, intensity);
            }
        });

        // Mover
        const mover = this.fixtures.movers[moverIndex];
        if (mover) {
            this.setMoverPosition(update, mover, panCenter, 190); // Tilt Front
            this.setMoverIntensity(update, mover, intensity, undefined);
            this.setMoverColor(update, mover, intensity, intensity, intensity, 0);
        }

        this.universe.update(update);
        
        if (mover) {
            this.animFocusScan(mover, panCenter, sceneID);
        }
    }

    animFocusScan(addr, centerPan, sceneID) {
        // console.log(`🔍 animFocusScan: Addr=${addr}, Center=${centerPan}, Scene=${sceneID}`);
        
        const left = {}; const right = {};
        left[addr] = centerPan - 10; 
        right[addr] = centerPan + 10;
        
        const loop = () => {
             if (this.activeProgram !== sceneID) return;
             this.currentAnimation = new this.dmx.animation()
                .add(right, 4000) // pan right
                .add(left, 4000)  // pan left
                .run(this.universe, loop);
        };
        loop();
    }

    scenePolice() {
        this.stopAnimation();
        this.activeProgram = 'police';
        console.log('💡 Scene: POLICE 🚨 (Final)');

        // 1. Initial Mover Setup (Dimmer On, Shutter Open, Tilt Horizontal)
        const init = {};
        this.fixtures.movers.forEach(addr => {
            this.setMoverPosition(init, addr, undefined, 127); // Tilt Center
            this.setMoverIntensity(init, addr, 255, 255);      // Dimmer Full, Shutter Open
        });
        this.universe.update(init);

        // 2. Bars & Washes (Fast Random: R, B, Off)
        this.policeInterval = setInterval(() => {
             if(this.activeProgram !== 'police') { clearInterval(this.policeInterval); return; }
             
             const update = {};
             // Colors: Red, Blue, Off
             const colors = [
                 {r:255, g:0, b:0}, // Red
                 {r:0, g:0, b:255}, // Blue
                 {r:0, g:0, b:0}    // Off
             ];
             
             // Bars
             this.fixtures.bars.forEach(addr => {
                 for(let i=0; i<8; i++) {
                     const c = colors[Math.floor(Math.random() * colors.length)];
                     this.setBarPixel(update, addr+(i*6), c.r, c.g, c.b);
                 }
             });
             
             // Washes
             this.fixtures.washes.forEach(addr => {
                 const c = colors[Math.floor(Math.random() * colors.length)];
                 this.setWashPixel(update, addr, c.r, c.g, c.b);
             });
             
             this.universe.update(update);
        }, 80); 
        
        this.animPoliceSweep();
    }

    animPoliceSweep() {
        this.activeMoverAnims = {}; // Reset tracker
        
        const red = this.Colors.RED;
        const blue = this.Colors.BLUE;

        this.fixtures.movers.forEach((addr, i) => {
             // Random Speed: 600ms - 800ms
             const speed = 600 + Math.random() * 200;
             // Random Start Delay: 0 - 500ms
             const delay = Math.random() * 500;

             const build = (pan, color) => ({
                 [addr]: pan,
                 [addr+5]: 255, [addr+6]: 255,
                 [addr+7]: color.r, [addr+8]: color.g, [addr+9]: color.b, [addr+10]: color.w
             });

             const doLeft = () => {
                 if(this.activeProgram !== 'police') return;
                 // Switch Blue @ 192, Sweep to 64
                 const start = build(192, blue);
                 const end = build(64, blue);
                 
                 const anim = new this.dmx.animation()
                     .add(start, 5) // Snap Color
                     .add(end, speed)
                     .run(this.universe, doRight);
                 this.activeMoverAnims[addr] = anim;
             };

             const doRight = () => {
                 if(this.activeProgram !== 'police') return;
                 // Switch Red @ 64, Sweep to 192
                 const start = build(64, red);
                 const end = build(192, red);
                 
                 const anim = new this.dmx.animation()
                     .add(start, 5) // Snap Color
                     .add(end, speed)
                     .run(this.universe, doLeft);
                 this.activeMoverAnims[addr] = anim;
             };

             // Start with random delay managed by addTimeout handles
             this.addTimeout(() => doRight(), delay);
        });
    }
}

module.exports = LightingEngine;
