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

// Deterministic timers
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
const WASH_PATCH = [
    { name: 'W1', addr: 232, footprint: 3 },
    { name: 'W2', addr: 236, footprint: 3 },
    { name: 'W3', addr: 240, footprint: 3 },
    { name: 'W4', addr: 244, footprint: 3 }
];

const MOVER_PATCH = [
    { name: 'M1', addr: 247, footprint: 14 },
    { name: 'M2', addr: 261, footprint: 14 },
    { name: 'M3', addr: 275, footprint: 14 }
];

// Helper: does an address belong to a mover?
function isMoverAddress(addr) {
    return MOVER_PATCH.some(m => addr >= m.addr && addr < m.addr + m.footprint);
}

// ---------------- TESTS ----------------
let pass = true;

// -------- TEST 1: BLACKOUT --------
console.log("\n--- TEST: BLACKOUT (WASHES) ---");
lastUpdate = {};
const e1 = new LightingEngine(mockDmx);
e1.blackout();
drainTimers();

WASH_PATCH.forEach(w => {
    const addr = w.addr;

    if (lastUpdate[addr] !== 0 || lastUpdate[addr+1] !== 0 || lastUpdate[addr+2] !== 0) {
        console.error(`Wash ${w.name} @${addr} RGB not 0 in BLACKOUT`);
        pass = false;
    }

    const ch4 = addr + 3; // the intentional gap channel
    if (!isMoverAddress(ch4) && lastUpdate[ch4] !== undefined) {
        console.error(`Wash ${w.name} @${addr} Ch4 ghost write in BLACKOUT (addr ${ch4})`);
        pass = false;
    }
});

// -------- TEST 2: HELL --------
console.log("\n--- TEST: HELL (WASHES) ---");
lastUpdate = {};
const e2 = new LightingEngine(mockDmx);
e2.sceneHell();

// New spec: HELL washes are solid RED (255,0,0)
WASH_PATCH.forEach(w => {
    const addr = w.addr;

    if (lastUpdate[addr] !== 255 || lastUpdate[addr+1] !== 0 || lastUpdate[addr+2] !== 0) {
        console.error(`Wash ${w.name} @${addr} incorrect RGB in HELL (expected RED 255,0,0)`);
        pass = false;
    }

    const ch4 = addr + 3;
    if (!isMoverAddress(ch4) && lastUpdate[ch4] !== undefined) {
        console.error(`Wash ${w.name} @${addr} Ch4 ghost write in HELL (addr ${ch4})`);
        pass = false;
    }
});

// -------- TEST 3: SUNSHINE --------
console.log("\n--- TEST: SUNSHINE (WASHES) ---");
lastUpdate = {};
const e3 = new LightingEngine(mockDmx);
e3.sceneSunshine();

WASH_PATCH.forEach(w => {
    const addr = w.addr;

    if (lastUpdate[addr] === undefined) {
        console.error(`Wash ${w.name} @${addr} not written in SUNSHINE`);
        pass = false;
    }

    const ch4 = addr + 3;
    if (!isMoverAddress(ch4) && lastUpdate[ch4] !== undefined) {
        console.error(`Wash ${w.name} @${addr} Ch4 ghost write in SUNSHINE (addr ${ch4})`);
        pass = false;
    }
});

// ---------------- RESULT ----------------
if (pass) {
    console.log("\n>> WASHES STRICT PROOF PASSED (ownership-aware, HELL=RED)");
} else {
    console.error("\n>> WASHES STRICT PROOF FAILED");
    process.exit(1);
}
