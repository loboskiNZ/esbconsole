const LightingEngine = require('./lighting-engine');

// Minimal DMX mock (no output needed)
const mockUniverse = { update(){}, updateAll(){} };
const mockDmx = {
    addUniverse: () => mockUniverse,
    animation: class {
        add(){ return this; }
        run(){ return this; }
        stop(){}
        delay(){ return this; }
    }
};

// Instantiate engine to read patch
const engine = new LightingEngine(mockDmx);
const fixtures = engine.fixtures;

console.log("Checking Patch Overlaps (LIVE engine.fixtures)...");

const map = new Map();
let collision = false;

const claim = (name, start, count) => {
    for (let i = 0; i < count; i++) {
        const addr = start + i;
        if (map.has(addr)) {
            console.error(`COLLISION @ ${addr}: ${map.get(addr)} vs ${name}`);
            collision = true;
        } else {
            map.set(addr, name);
        }
    }
};

// Bars: 48ch
fixtures.bars.forEach((addr, i) => claim(`Bar${i}`, addr, 48));

// Movers: 14ch
fixtures.movers.forEach((addr, i) => claim(`Mover${i}`, addr, 14));

// Washes: 3ch (RGB)
fixtures.washes.forEach((addr, i) => claim(`Wash${i}`, addr, 3));

if (collision) {
    console.log("Result: COLLISIONS FOUND");
    process.exit(1);
} else {
    console.log("Result: CLEAN PATCH");
    process.exit(0);
}
