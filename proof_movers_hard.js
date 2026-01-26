const LightingEngine = require('./lighting-engine');

// ---------------- MOCKS ----------------
let lastUpdate = {};

const mockUniverse = {
  update: (u) => { lastUpdate = { ...lastUpdate, ...u }; },
  updateAll: () => {}
};

const mockDmx = {
  addUniverse: () => mockUniverse,
  animation: class {
    add(obj) { lastUpdate = { ...lastUpdate, ...obj }; return this; }
    run() { return this; }     // ignore callbacks; we only care about channel writes
    stop() {}
    delay() { return this; }
  }
};

// Intervals fire once (deterministic)
global.setInterval = (cb) => { cb(); return 999; };
global.clearInterval = () => {};

// Hardened timeout mock: queue + drain later
const timeoutQueue = [];
global.setTimeout = (cb) => { timeoutQueue.push(cb); return Math.floor(Math.random() * 100000); };
global.clearTimeout = () => {};

function drainTimeouts(limit = 500) {
  let n = 0;
  while (timeoutQueue.length && n < limit) {
    const cb = timeoutQueue.shift();
    cb();
    n++;
  }
  if (timeoutQueue.length) {
    console.error(`WARN: drainTimeouts hit limit=${limit}. Remaining=${timeoutQueue.length}`);
  }
}

// ---------------- OWNERSHIP PROOF ----------------
// Bars: 48ch each (1..192)
// Washes: 3ch each (RGB only; channel+3 is intentionally a gap)
// Movers: 14ch each
function buildOwnedRanges(engine) {
  const ranges = [];

  engine.fixtures.bars.forEach(addr => ranges.push({ name: `bar@${addr}`, start: addr, end: addr + 48 - 1 }));
  engine.fixtures.washes.forEach(addr => ranges.push({ name: `wash@${addr}`, start: addr, end: addr + 3 - 1 }));
  engine.fixtures.movers.forEach(addr => ranges.push({ name: `mover@${addr}`, start: addr, end: addr + 14 - 1 }));

  return ranges;
}

function isOwnedChannel(ch, ranges) {
  return ranges.some(r => ch >= r.start && ch <= r.end);
}

function assertNoUnownedWrites(context, ranges) {
  const keys = Object.keys(lastUpdate).map(Number);
  const bad = keys.filter(k => !isOwnedChannel(k, ranges)).sort((a, b) => a - b);

  if (bad.length) {
    console.error(`FAIL: ${context}: wrote to unowned channels: ${bad.join(', ')}`);
    return false;
  }
  return true;
}

// ---------------- MOVER HARD PROOF ----------------
function moverHardProof(context, engine) {
  const movers = engine.fixtures.movers;
  const keys = Object.keys(lastUpdate).map(Number);

  let ok = true;

  movers.forEach(start => {
    // Keys written in mover's legal 14ch range:
    const inRange = keys.filter(k => k >= start && k < start + 14).sort((a, b) => a - b);

    // Offsets written (what matters)
    const offsets = inRange.map(k => k - start);
    const badOffsets = offsets.filter(o => o < 0 || o > 13);

    console.log(`Mover ${start} (${context}): wrote offsets [${offsets.join(', ')}]`);

    if (badOffsets.length) {
      console.error(`FAIL: ${context}: mover ${start} wrote illegal offsets: ${badOffsets.join(', ')}`);
      ok = false;
    }
  });

  return ok;
}

// ---------------- TESTS ----------------
let pass = true;

console.log("\n--- TEST: MOVERS HARD PROOF (HELL + POLICE, ownership-safe) ---");

// Create engine once so fixtures patch is the source of truth
const engine = new LightingEngine(mockDmx);
const owned = buildOwnedRanges(engine);

// -------- TEST 1: HELL --------
console.log("\n--- SCENE: HELL ---");
lastUpdate = {};
engine.sceneHell();
drainTimeouts(); // hell doesn't use addTimeout, but safe

if (!assertNoUnownedWrites("sceneHell", owned)) pass = false;
if (!moverHardProof("sceneHell", engine)) pass = false;

// Optional sanity: expect core mover keys in Hell
engine.fixtures.movers.forEach(start => {
  const expect = [0, 1, 5, 6, 7, 8, 9, 10];
  const missing = expect.filter(o => lastUpdate[start + o] === undefined);
  if (missing.length) {
    console.error(`WARN: sceneHell: mover ${start} missing expected keys: ${missing.join(', ')}`);
  }
});

// -------- TEST 2: POLICE --------
console.log("\n--- SCENE: POLICE ---");
lastUpdate = {};
engine.scenePolice();
drainTimeouts(); // police uses addTimeout -> doRight()

if (!assertNoUnownedWrites("scenePolice", owned)) pass = false;
if (!moverHardProof("scenePolice", engine)) pass = false;

// ---------------- RESULT ----------------
if (pass) {
  console.log("\n>> MOVERS HARD PROOF PASSED (offsets + ownership)");
} else {
  console.error("\n>> MOVERS HARD PROOF FAILED");
  process.exit(1);
}
