const LightingEngine = require('./lighting-engine');

// ---------------- MOCKS ----------------
let lastUpdate = {};

const mockUniverse = {
    update: (u) => {
        lastUpdate = { ...lastUpdate, ...u };
    },
    updateAll: () => {}
};

const mockDmx = {
    addUniverse: () => mockUniverse,
    animation: class {
        add(obj) {
            lastUpdate = { ...lastUpdate, ...obj };
            return this;
        }
        run() { return this; }
        stop() {}
        delay() { return this; }
    }
};

// Deterministic timers (no TDZ crash)
const timeoutQueue = [];
global.setTimeout = (cb) => {
    timeoutQueue.push(cb);
    return 1;
};
global.clearTimeout = () => {};
global.setInterval = (cb) => { cb(); return 1; };
global.clearInterval = () => {};

function drainTimers(limit = 50) {
    let i = 0;
    while (timeoutQueue.length && i < limit) {
        timeoutQueue.shift()();
        i++;
    }
}

// ---------------- PATCH DEFINITIONS ----------------
const washes = [232, 236, 240, 244];

const MOVER_PATCH = [
    { name: 'Mover 1', base: 247, footprint: 14 },
    { name: 'Mover 2', base: 261, footprint: 14 },
    { name: 'Mover 3', base: 275, footprint: 14 }
];

// Helper: does an address belong to a mover?
function isMoverAddress(addr) {
    return MOVER_PATCH.some(m => addr >= m.base && addr < m.base + m.footprint);
}

// ---------------- TESTS ----------------
let pass = true;

// -------- TEST 1: BLACKOUT --------
console.log("\n--- TEST: BLACKOUT ---");
lastUpdate = {};
const e1 = new LightingEngine(mockDmx);
e1.blackout();
drainTimers();

washes.forEach(addr => {
    if (lastUpdate[addr] !== 0 || lastUpdate[addr+1] !== 0 || lastUpdate[addr+2] !== 0) {
        console.error(`Wash ${addr} RGB not 0 in BLACKOUT`);
        pass = false;
    }

    const ch4 = addr + 3;
    if (!isMoverAddress(ch4) && lastUpdate[ch4] !== undefined) {
        console.error(`Wash ${addr} Ch4 ghost write in BLACKOUT (addr ${ch4})`);
        pass = false;
    }
});

// -------- TEST 2: HELL --------
console.log("\n--- TEST: HELL ---");
lastUpdate = {};
const e2 = new LightingEngine(mockDmx);
e2.sceneHell();

washes.forEach(addr => {
    if (lastUpdate[addr] !== 255 || lastUpdate[addr+1] !== 120 || lastUpdate[addr+2] !== 0) {
        console.error(`Wash ${addr} incorrect RGB in HELL`);
        pass = false;
    }

    const ch4 = addr + 3;
    if (!isMoverAddress(ch4) && lastUpdate[ch4] !== undefined) {
        console.error(`Wash ${addr} Ch4 ghost write in HELL (addr ${ch4})`);
        pass = false;
    }
});

// -------- TEST 3: SUNSHINE --------
console.log("\n--- TEST: SUNSHINE ---");
lastUpdate = {};
const e3 = new LightingEngine(mockDmx);
e3.sceneSunshine();

washes.forEach(addr => {
    if (lastUpdate[addr] === undefined) {
        console.error(`Wash ${addr} not written in SUNSHINE`);
        pass = false;
    }

    const ch4 = addr + 3;
    if (!isMoverAddress(ch4) && lastUpdate[ch4] !== undefined) {
        console.error(`Wash ${addr} Ch4 ghost write in SUNSHINE (addr ${ch4})`);
        pass = false;
    }
});

// ---------------- RESULT ----------------
if (pass) {
    console.log("\n>> WASHES STRICT PROOF PASSED (ownership-aware)");
} else {
    console.error("\n>> WASHES STRICT PROOF FAILED");
    process.exit(1);
}
