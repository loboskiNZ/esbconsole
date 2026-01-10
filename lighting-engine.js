const DMX = require('dmx');

class LightingEngine {
    constructor(dmxInstance, universeName = 'demo', driver = 'null') {
        this.dmx = dmxInstance;
        // Use 'null' driver for now (virtual only) unless hardware exists
        this.universe = this.dmx.addUniverse(universeName, driver);
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
        // 4x Bars (24ch each) -> 1, 25, 49, 73
        // 3x Movers (13ch? 100, 113, 126)
        // 4x Washes (4ch? 150, 154, 158, 162)
        this.fixtures = {
            bars: [1, 25, 49, 73], // Start addresses
            movers: [100, 113, 126],
            washes: [150, 154, 158, 162]
        };
    }

    // --- UTILS ---
    update(channels) {
        this.universe.update(channels);
    }
    
    addTimeout(fn, delay) {
        const t = setTimeout(() => {
            fn();
            // Remove self from list after running
            this.timeouts = this.timeouts.filter(id => id !== t);
        }, delay);
        this.timeouts.push(t);
        return t;
    }

    fillRange(start, count, val) {
        const up = {};
        for(let i=0; i<count; i++) up[start+i] = val;
        return up;
    }

    // --- SCENES / PROGRAMS ---

    blackout() {
        this.stopAnimation();
        this.activeProgram = 'blackout';
        console.log('💡 Scene: Fade to Black');

        const end = {};

        // Bars & Washes: All Channels to 0
        this.fixtures.bars.forEach(addr => {
            for(let i=0; i<24; i++) end[addr+i] = 0;
        });
        this.fixtures.washes.forEach(addr => {
            for(let i=0; i<4; i++) end[addr+i] = 0;
        });

        // Movers: Dimmer/Color -> 0, Position -> Center/Horizontal (127)
        this.fixtures.movers.forEach(addr => {
             end[addr+0] = 127; // Pan Center
             end[addr+1] = 127; // Tilt Horizontal
             end[addr+5] = 0;   // Dimmer Off
             end[addr+7] = 0;   // R
             end[addr+8] = 0;   // G
             end[addr+9] = 0;   // B
             end[addr+10]= 0;   // W
        });

        // Animate 1 second fade
        this.currentAnimation = new this.dmx.animation()
            .add(end, 1000)
            .run(this.universe);
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
                default: console.warn('Unknown Scene:', sceneName);
            }
        } catch (err) {
            console.error('❌ Error switching scene:', err);
        }
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
        
        // Bars: Split Red / Amber per SEGMENT (8 segments per bar)
        const c1 = {r:255, g:0, b:0}; // Red
        const c2 = {r:255, g:120, b:0}; // Amber
        
        this.fixtures.bars.forEach(startAddr => {
             for(let seg=0; seg<8; seg++) {
                 // Determine pattern: Even/Odd segments swap colors
                 const isRed = this.sunState ? (seg % 2 === 0) : (seg % 2 !== 0);
                 const color = isRed ? c1 : c2;
                 
                 const base = startAddr + (seg * 3);
                 update[base] = color.r;
                 update[base+1] = color.g;
                 update[base+2] = color.b;
             }
        });

        // Washes: Double Time (Every 2nd beat)
        const washState = Math.floor(this.sunPulseCount / 2) % 2 === 0;
        const dimHigh = 0.75;
        const dimLow = 0.60;
        
        this.fixtures.washes.forEach((addr, i) => {
            // Swap based on index and washState
            const isHigh = washState ? (i % 2 === 0) : (i % 2 !== 0);
            const dim = isHigh ? dimHigh : dimLow;

            update[addr] = Math.floor(255 * dim);     // R
            update[addr+1] = Math.floor(200 * dim);   // G
            update[addr+2] = 0; // B
            update[addr+3] = 0; // W
        });

        // Soft Wash Transition (500ms)
        this.beatAnim = new this.dmx.animation()
            .add(update, 500)
            .run(this.universe);
    }

    sceneSetup() {
        // All White Fade In
        this.stopAnimation();

        const init = {};   // Instant (Position, Shutter)
        const start = {};  // Fade Start (Dimmer = 0)
        const end = {};    // Fade End (Dimmer = 255)

        // Bars: Fade 0 -> 255
        this.fixtures.bars.forEach(addr => {
            for(let i=0; i<24; i++) {
                start[addr+i] = 0;
                end[addr+i] = 255;
            }
        });

        // Washes: Fade 0 -> 255
        this.fixtures.washes.forEach(addr => {
            for(let i=0; i<4; i++) {
                start[addr+i] = 0;
                end[addr+i] = 255;
            }
        });

        // Movers: Position Instant, Dimmer Fade
        this.fixtures.movers.forEach(addr => {
             // Instant
             init[addr + 0] = 127; // Pan Center
             init[addr + 1] = 0;   // Tilt Up
             init[addr + 6] = 255; // Shutter Open
             init[addr + 7] = 255; // R
             init[addr + 8] = 255; // G
             init[addr + 9] = 255; // B
             init[addr + 10] = 255;// W

             // Fade
             start[addr + 5] = 0;  // Dimmer Start
             end[addr + 5] = 255;  // Dimmer End
        });

        // Apply Instant & Start
        this.universe.update({...init, ...start});
        this.activeProgram = 'setup';

        // Animate
        this.currentAnimation = new this.dmx.animation()
            .add(end, 1500)
            .run(this.universe);
    }

    sceneHell() {
        this.stopAnimation();
        this.activeProgram = 'hell';
        console.log('💡 Scene: HELL (Intro)');

        const init = {}; 
        const start = {};
        const end = {};

        // Bars: Red (75%)
        this.fixtures.bars.forEach(addr => {
            for(let i=0; i<24; i+=3) { 
                 start[addr+i] = 0;   end[addr+i] = 191; 
                 start[addr+i+1] = 0; end[addr+i+1] = 0; 
                 start[addr+i+2] = 0; end[addr+i+2] = 0; 
            }
        });

        // Washes: Amber (75%)
        this.fixtures.washes.forEach(addr => {
            start[addr] = 0;   end[addr] = 191; 
            start[addr+1] = 0; end[addr+1] = 90; 
            start[addr+2] = 0; end[addr+2] = 0; 
            start[addr+3] = 0; end[addr+3] = 0; 
        });

        // Movers: Red, Horizontal (Tilt 127)
        this.fixtures.movers.forEach(addr => {
             init[addr+0] = 127; // Pan
             init[addr+1] = 127; // Tilt Horizontal
             init[addr+6] = 255; // Shutter
             
             init[addr+7] = 255; // R
             init[addr+8] = 0;   // G
             init[addr+9] = 0;   // B
             init[addr+10] = 0;  // W

             start[addr+5] = 0;  end[addr+5] = 191; // Dimmer -> 75%
        });
        
        this.universe.update({...init, ...start});

        // 1. Fade In
        this.currentAnimation = new this.dmx.animation()
            .add(end, 500)
            .run(this.universe, () => {
             if(this.activeProgram !== 'hell') return;
             // 2. Move Up
             console.log('💡 Scene: HELL (Move Up)');
             const move = {};
             this.fixtures.movers.forEach(addr => {
                 move[addr+1] = 64; // Tilt Up 45deg
             });
             this.currentAnimation = new this.dmx.animation()
                .add(move, 1000)
                .run(this.universe, () => {
                     if(this.activeProgram !== 'hell') return;
                     // 3. Loop
                     this.animHellWobble();
                });
        });
    }

    animHellWobble() {
        const low = {};
        const high = {};
        this.fixtures.movers.forEach(addr => {
             low[addr+1] = 59; 
             high[addr+1] = 69;
        });

        const loop = () => {
            if (this.activeProgram !== 'hell') return;
            this.currentAnimation = new this.dmx.animation()
                .add(high, 500)
                .delay(100)
                .add(low, 500)
                .delay(100)
                .run(this.universe, loop);
        }
        loop();
    }

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
        
        // 1. Gather Pool (32 Bar Segs + 4 Washes + 3 Movers)
        this.fixtures.bars.forEach(addr => {
            for(let i=0; i<8; i++) pixels.push({ type: 'rgb', addr: addr + (i*3) });
        });
        this.fixtures.washes.forEach(addr => pixels.push({ type: 'rgbw', addr }));
        this.fixtures.movers.forEach(addr => pixels.push({ type: 'mover', addr }));
        
        // 2. Default: All OFF (Blackout base)
        this.fixtures.bars.forEach(addr => { for(let i=0; i<24; i++) update[addr+i] = 0; });
        this.fixtures.washes.forEach(addr => { for(let i=0; i<4; i++) update[addr+i] = 0; });
        this.fixtures.movers.forEach(addr => { 
            // Keep Position (Don't reset)
            update[addr+5] = 0;   // Dimmer Off
            update[addr+6] = 255; // Shutter Open
            update[addr+7] = 0;   // R
            update[addr+8] = 0;   // G
            update[addr+9] = 0;   // B
            update[addr+10] = 0;  // W
        });

        // 3. Select Random Winners (Min 3, Max 12)
        const count = Math.floor(Math.random() * 10) + 3; 
        for(let k=0; k<count; k++) {
            const idx = Math.floor(Math.random() * pixels.length);
            const p = pixels[idx];
            
            // Turn ON (White)
            if (p.type === 'rgb') {
                update[p.addr] = 255; update[p.addr+1] = 255; update[p.addr+2] = 255;
            } else if (p.type === 'rgbw') {
                update[p.addr] = 255; update[p.addr+1] = 255; update[p.addr+2] = 255; update[p.addr+3] = 255;
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
             update[addr+0] = 127; // Pan Center
             update[addr+1] = 80;  // Tilt Init
             update[addr+5] = 255; // Dimmer Max
             update[addr+6] = 255; // Shutter Open
             update[addr+7] = 255; // R
             update[addr+8] = 255; // G
             update[addr+9] = 0;   // B
             update[addr+10]= 50;  // W
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
        console.log('💡 Scene: AQUA');
        
        // Setup Wave Pixels (Cyan)
        this.wavePixels = [];
        const speedBase = 0.001; const speedVar = 0.002;
        
        this.fixtures.bars.forEach(addr => {
            for(let i=0; i<8; i++) {
                this.wavePixels.push({
                    type: 'rgb', addr: addr + (i*3),
                    phase: Math.random() * Math.PI * 2,
                    speed: speedBase + (Math.random() * speedVar),
                    r: 0, g: 1, b: 1 // Cyan
                });
            }
        });
        this.fixtures.washes.forEach(addr => {
            this.wavePixels.push({
                type: 'rgbw', addr: addr,
                phase: Math.random() * Math.PI * 2,
                speed: speedBase + (Math.random() * speedVar),
                r: 0, g: 1, b: 1 // Cyan
            });
        });

        this.animOrganicWave(); 
        // Bubbles: Cyan
        this.animRandomSplashes('aqua', {r:0, g:255, b:255, w:0});
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
                    type: 'rgb', addr: addr + (i*3),
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
            // No strict activeProgram check needed if we rely on stopAnimation clearing interval
            
            const now = Date.now();
            const update = {};
            
            this.wavePixels.forEach(p => {
                const val = Math.sin(now * p.speed + p.phase); 
                // Map -1..1 to Intensity 50..220
                const rawBright = 135 + (val * 85); 
                
                const r = Math.floor(rawBright * p.r);
                const g = Math.floor(rawBright * p.g);
                const b = Math.floor(rawBright * p.b);
                
                if (p.type === 'rgb') {
                    update[p.addr] = r; update[p.addr+1] = g; update[p.addr+2] = b;
                } else if (p.type === 'rgbw') {
                    update[p.addr] = r; update[p.addr+1] = g; update[p.addr+2] = b; update[p.addr+3] = 0;
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

         setTimeout(() => {
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
        this.fixtures.bars.forEach(addr => { for(let i=0; i<24; i++) update[addr+i] = 0; });
        this.fixtures.washes.forEach(addr => { for(let i=0; i<4; i++) update[addr+i] = 0; });
        this.fixtures.movers.forEach(addr => {
            update[addr] = 127; update[addr+1] = 127; 
            update[addr+5] = 0; update[addr+6] = 255;
            update[addr+7] = 0; update[addr+8] = 0; update[addr+9] = 0; update[addr+10] = 0;
        });

        // Bars
        if (barIndices[0] !== undefined) {
            const bar = this.fixtures.bars[barIndices[0]];
            for(let i=4; i<8; i++) {
                 const base = bar + (i*3);
                 update[base] = intensity; update[base+1] = intensity; update[base+2] = intensity;
            }
        }
        if (barIndices[1] !== undefined) {
             const bar = this.fixtures.bars[barIndices[1]];
             for(let i=0; i<4; i++) {
                 const base = bar + (i*3);
                 update[base] = intensity; update[base+1] = intensity; update[base+2] = intensity;
            }
        }

        // Washes
        washIndices.forEach(idx => {
            const addr = this.fixtures.washes[idx];
            if (addr) {
                update[addr] = intensity; update[addr+1] = intensity; update[addr+2] = intensity; update[addr+3] = intensity;
            }
        });

        // Mover
        const mover = this.fixtures.movers[moverIndex];
        if (mover) {
            update[mover] = panCenter; 
            update[mover+1] = 190; // Tilt Front
            update[mover+5] = intensity; update[mover+7] = intensity; update[mover+8] = intensity; update[mover+9] = intensity; update[mover+10]= 0;
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
        console.log('💡 Scene: POLICE 🚨 (Refined)');

        // 1. Initial Mover Setup (Dimmer On, Shutter Open, Tilt Horizontal)
        const init = {};
        this.fixtures.movers.forEach(addr => {
             init[addr+1] = 127; // Tilt Center
             init[addr+5] = 255; // Dimmer Full
             init[addr+6] = 255; // Shutter Open
        });
        this.universe.update(init);

        // 2. Bars & Washes: Fast Random Strobe (Interval)
        this.policeInterval = setInterval(() => {
             if(this.activeProgram !== 'police') { clearInterval(this.policeInterval); return; }
             
             const update = {};
             const colors = [
                 [this.Colors.RED.r, this.Colors.RED.g, this.Colors.RED.b], 
                 [this.Colors.BLUE.r, this.Colors.BLUE.g, this.Colors.BLUE.b], 
                 [this.Colors.OFF.r, this.Colors.OFF.g, this.Colors.OFF.b]
             ];
             
             // Randomize Bars (Segmented)
             this.fixtures.bars.forEach(addr => {
                 for(let i=0; i<8; i++) { // 8 Segments
                     const c = colors[Math.floor(Math.random() * colors.length)];
                     const base = addr + (i*3);
                     update[base] = c[0];
                     update[base+1] = c[1];
                     update[base+2] = c[2];
                 }
             });
             
             // Randomize Washes (Whole fixture)
             this.fixtures.washes.forEach(addr => {
                 const c = colors[Math.floor(Math.random() * colors.length)];
                 update[addr] = c[0];
                 update[addr+1] = c[1];
                 update[addr+2] = c[2];
                 update[addr+3] = 0;
             });
             
             this.universe.update(update);
        }, 80); // Fast Random Flickering
        
        // 3. Movers: Sweep Animation
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
