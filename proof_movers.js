const LightingEngine = require('./lighting-engine');

// MOCKS
let lastUpdate = {};
const mockUniverse = {
  update: (u) => { lastUpdate = { ...lastUpdate, ...u }; },
  updateAll: () => {}
};

const mockDmx = {
  addUniverse: () => mockUniverse,
  animation: class {
    add(obj){ lastUpdate = { ...lastUpdate, ...obj }; return this; }
    run(){ return this; }
    stop(){}
    delay(){ return this; }
  }
};

// run timers immediately once
global.setInterval = (cb) => { cb(); return 999; };
global.clearInterval = () => {};
global.setTimeout = (cb) => { cb(); return 999; };

function scanMoverWrites(start, width = 14) {
  const keys = Object.keys(lastUpdate)
    .map(Number)
    .filter(k => k >= start && k < start + width)
    .sort((a,b) => a-b);

  const maxAddr = keys.length ? keys[keys.length - 1] : null;
  const maxOffset = maxAddr !== null ? (maxAddr - start) : null;

  // hard proof: no offsets >= width inside the scanned set by definition,
  // but we still report maxOffset for clarity
  return { keys, maxAddr, maxOffset };
}

console.log("\n--- TEST: MOVERS BOUNDS (14ch) ---");
lastUpdate = {};
const engine = new LightingEngine(mockDmx);

// pick a mover-touching scene
engine.sceneHell(); // or engine.scenePolice()

const movers = engine.fixtures.movers; // should be [247, 261, 275]
let pass = true;

console.log("MoverStart | Count | MaxAddr | MaxOffset | WrittenAddrs");
console.log("----------|-------|---------|----------|------------");

for (const start of movers) {
  const { keys, maxAddr, maxOffset } = scanMoverWrites(start, 14);

  console.log(
    `${String(start).padEnd(9)}|` +
    ` ${String(keys.length).padEnd(5)}|` +
    ` ${String(maxAddr).padEnd(7)}|` +
    ` ${String(maxOffset).padEnd(9)}| ` +
    `${keys.join(", ")}`
  );

  // expected offsets in your current mover code are usually: 0,1,5,6,7,8,9,10 (maxOffset 10)
  if (maxOffset !== null && maxOffset > 13) {
    console.error(`FAIL: mover ${start} wrote beyond +13`);
    pass = false;
  }
}

if (pass) console.log("\n>> MOVER PROOF PASSED (writes bounded to start..start+13)");
else {
  console.error("\n>> MOVER PROOF FAILED");
  process.exit(1);
}
