
const LightingEngine = require('./lighting-engine');

// MOCKS
const updates = [];
const mockUniverse = {
    update: (u) => { 
        updates.push(u);
    },
    updateAll: () => {}
};

const mockDmx = {
    addUniverse: () => mockUniverse,
    animation: class { 
        add(){return this} 
        run(){return this} 
        stop(){} 
    }
};

// Mock Timers & Date
let storedCallback = null;
global.setInterval = (cb, ms) => {
    storedCallback = cb;
    return 999;
};
global.clearInterval = () => {};

// Lock Time
const FIXED_TIME = 1000000000000;
global.Date.now = () => FIXED_TIME;

// INSTANTIATE
const engine = new LightingEngine(mockDmx);

// RUN
console.log("Running sceneAqua()...");
engine.sceneAqua();

if (!storedCallback) {
    console.error("ERROR: No interval started.");
    process.exit(1);
}

// EXECUTE FRAME (t=0)
storedCallback();

if (updates.length === 0) {
    console.error("ERROR: No DMX updates captured.");
    process.exit(1);
}

const frame = updates[updates.length - 1];

// PRINT DIAGNOSTIC TABLE
console.log("\nDIAGNOSTIC TABLE (t=0)");
console.log("-----------------------------------------------------------------------------------------------------");
console.log("| Fix | Cell | p (lin) | Dim (1/255) | Str (2/0) | Red (3/0) | Grn (4) | Blu (5) | Pgm (6/0) | Abs Addrs |");
console.log("-----------------------------------------------------------------------------------------------------");

const fixStarts = [1, 49, 97, 145];

for (let f = 0; f < 4; f++) {
    for (let c = 0; c < 8; c++) {
        const p = f * 8 + c;
        const base = fixStarts[f] + (c * 6);
        
        const dimVal = frame[base + 0];
        const strVal = frame[base + 1];
        const redVal = frame[base + 2];
        const grnVal = frame[base + 3];
        const bluVal = frame[base + 4];
        const pgmVal = frame[base + 5];

        console.log(
            `| ${f.toString().padEnd(3)} ` +
            `| ${c.toString().padEnd(4)} ` +
            `| ${p.toString().padEnd(7)} ` +
            `| ${dimVal.toString().padEnd(11)} ` +
            `| ${strVal.toString().padEnd(9)} ` +
            `| ${redVal.toString().padEnd(9)} ` +
            `| ${grnVal.toString().padEnd(7)} ` +
            `| ${bluVal.toString().padEnd(7)} ` +
            `| ${pgmVal.toString().padEnd(9)} ` +
            `| ${base}-${base+5} |`
        );
    }
}
console.log("-----------------------------------------------------------------------------------------------------");

// ASSERTIONS
console.log("\nBug Check Confirmations (Self-Verify):");

// 1. Off by one
// Logic used: update[baseAddr + 0]. baseAddr is 1-based.
// If baseAddr=1, key is "1". Frame keys are strings/ints.
// Checking fixture 0 cell 0 (base=1). Dimmer=255 at key 1.
// If frame was array, index is 1?? No, dmx lib update uses object keys.
// { 1: 255, ... } means Channel 1 is 255.
// My logic uses 1-based keys.
console.log(`Dimmer F0C0 at Address 1: ${frame[1]}`);
// If frame[1] is 255, then it's correct for dmx library usage (usually 1-based object keys).

// 2. Swapping Green/Blue
console.log(`Green F0C0 at Addr 4: ${frame[4]}`);
console.log(`Blue  F0C0 at Addr 5: ${frame[5]}`);
if (frame[4] === frame[5]) console.log("Green/Blue Equal: YES");
else console.error("Green/Blue Equal: NO");

// 3. RGB Only?
console.log(`Red F0C0 at Addr 3: ${frame[3]}`); // Should be 0
console.log(`Prg F0C0 at Addr 6: ${frame[6]}`); // Should be 0

// 4. Stride
console.log(`Dimmer F1C0 at Addr 49: ${frame[49]}`); // Should be 255

// 5. Bounds
const keys = Object.keys(frame).map(Number).sort((a,b)=>a-b);
console.log(`Min Address: ${keys[0]}, Max Address: ${keys[keys.length-1]}`);

