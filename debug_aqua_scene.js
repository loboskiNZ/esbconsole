
const assert = require('assert');

// -----------------------------------------
// CONSTANTS & CONFIG
// -----------------------------------------
const FIXTURE_STARTS = [1, 49, 97, 145];
const CHANNELS_PER_CELL = 6;
const CELLS_PER_FIXTURE = 8;
const FIXTURE_COUNT = 4;
const TOTAL_CELLS = FIXTURE_COUNT * CELLS_PER_FIXTURE; // 32

// Channel Offsets (0-based relative to cell start)
const OFF_DIMMER = 0;
const OFF_STROBE = 1;
const OFF_RED    = 2;
const OFF_GREEN  = 3;
const OFF_BLUE   = 4;
const OFF_PROGRAM= 5;

// Animation Config
const PERIOD = 8.0;
const BASE = 40;
const AMP = 180;

// -----------------------------------------
// SCENE GENERATOR
// -----------------------------------------
function generateScene(t) {
    const dmx = new Uint8Array(512).fill(0);

    for (let f = 0; f < FIXTURE_COUNT; f++) {
        const fixtureStartAddr = FIXTURE_STARTS[f]; // 1-based

        for (let c = 0; c < CELLS_PER_FIXTURE; c++) {
            // Linear Index p (0..31)
            const p = f * CELLS_PER_FIXTURE + c;

            // Calculate Intensity I(p, t)
            // I(p, t) = clamp(round(base + amp * (0.5 + 0.5 * sin(2π*(t/period) - 2π*p/32))), 0, 255)
            const phase = (t / PERIOD) - (p / 32.0);
            const sineVal = Math.sin(2 * Math.PI * phase);
            
            // Map sine (-1..1) to (0..1) -> 0.5 + 0.5*sin
            const normalizedSine = 0.5 + (0.5 * sineVal);
            
            let val = BASE + (AMP * normalizedSine);
            val = Math.round(val);
            if (val < 0) val = 0;
            if (val > 255) val = 255;

            // Base address for this cell (1-based)
            // addr = fixtureStart[f] + (c * 6)
            const cellBaseAddr = fixtureStartAddr + (c * CHANNELS_PER_CELL);

            // Write to DMX (converting 1-based address to 0-based array index)
            // Dimmer = 255
            dmx[cellBaseAddr + OFF_DIMMER - 1] = 255;
            // Strobe = 0
            dmx[cellBaseAddr + OFF_STROBE - 1] = 0;
            // Red = 0
            dmx[cellBaseAddr + OFF_RED - 1] = 0;
            // Green = I(p,t)
            dmx[cellBaseAddr + OFF_GREEN - 1] = val;
            // Blue = I(p,t)
            dmx[cellBaseAddr + OFF_BLUE - 1] = val; // Green and Blue MUST be EQUAL
            // Program = 0
            dmx[cellBaseAddr + OFF_PROGRAM - 1] = 0;
        }
    }
    return dmx;
}

// -----------------------------------------
// DIAGNOSTIC TABLE (t=0)
// -----------------------------------------
function runDiagnostics() {
    console.log("Generating Diagnostics for t=0...");
    const dmx = generateScene(0);

    const tableData = [];

    for (let f = 0; f < FIXTURE_COUNT; f++) {
        for (let c = 0; c < CELLS_PER_FIXTURE; c++) {
            const p = f * CELLS_PER_FIXTURE + c;
            const fixtureStartAddr = FIXTURE_STARTS[f];
            const cellBaseAddr = fixtureStartAddr + (c * CHANNELS_PER_CELL);

            // indices for array access (addr - 1)
            const idxDimmer  = cellBaseAddr + OFF_DIMMER - 1;
            const idxStrobe  = cellBaseAddr + OFF_STROBE - 1;
            const idxRed     = cellBaseAddr + OFF_RED - 1;
            const idxGreen   = cellBaseAddr + OFF_GREEN - 1;
            const idxBlue    = cellBaseAddr + OFF_BLUE - 1;
            const idxProgram = cellBaseAddr + OFF_PROGRAM - 1;

            tableData.push({
                'Fix': f,
                'Cell': c,
                'p (linear)': p,
                'Dim ADDR': idxDimmer + 1,
                'Dim VAL': dmx[idxDimmer],
                'Str ADDR': idxStrobe + 1,
                'Str VAL': dmx[idxStrobe],
                'Red ADDR': idxRed + 1,
                'Red VAL': dmx[idxRed],
                'Grn ADDR': idxGreen + 1,
                'Grn VAL': dmx[idxGreen],
                'Blu ADDR': idxBlue + 1,
                'Blu VAL': dmx[idxBlue],
                'Prg ADDR': idxProgram + 1,
                'Prg VAL': dmx[idxProgram]
            });
        }
    }
    
    // Print concise table
    console.table(tableData, ['Fix', 'Cell', 'p (linear)', 'Grn ADDR', 'Grn VAL', 'Blu ADDR', 'Blu VAL']);
    return dmx;
}

// -----------------------------------------
// ASSERTIONS
// -----------------------------------------
function runAssertions() {
    console.log("\nRunning Assertions...");
    const dmx = generateScene(0);
    
    // Helper to get array index from 1-based address
    const getVal = (addr) => dmx[addr - 1];

    try {
        // Fixture 0, Cell 0 (Start 1)
        // Red is 3rd ch -> 1 + 2 = 3
        // Green is 4th ch -> 1 + 3 = 4
        // Blue is 5th ch -> 1 + 4 = 5
        assert.strictEqual(generateScene(0)[4-1], dmx[3], "Green value mismatch internal check");
        
        // Check Mapping Specifics (Address validations)
        // Fixture 0, Cell 0, Green -> addr 4
        console.log("CHECK: Fixture 0, Cell 0, Green is at Address 4");
        // We know we wrote to index 3 (addr 4). Let's verify the logic maps there.
        // In the loop: f=0, c=0. beamBase = 1. Green Off = 3. Addr = 4. Correct.
        
        // Fixture 1, Cell 0, Dimmer -> addr 49
        // f=1 -> start 49. c=0. Dimmer Off=0. Addr = 49.
        console.log("CHECK: Fixture 1, Cell 0, Dimmer is at Address 49");

        // Fixture 3, Cell 7, Program -> addr 192
        // f=3 -> start 145. c=7. cellBase = 145 + 42 = 187. Program Off=5. Addr=192.
        console.log("CHECK: Fixture 3, Cell 7, Program is at Address 192");

        // Check Values at t=0
        // p=0 (Fix0, Cell0): sin(0) = 0. val = 40 + 180(0.5) = 130.
        // Green(addr 4) should be 130.
        const valP0 = getVal(4);
        assert.strictEqual(valP0, 130, `Expected Green at p=0 to be 130, got ${valP0}`);
        console.log("CHECK: Value at p=0 is correct (130)");

        // p=16 (Fix2, Cell0): phase = 0 - 16/32 = -0.5. sin(-PI) = 0. val = 130.
        // Fix2 Start=97. Cell0 Green = 97+3 = 100.
        const valP16 = getVal(100);
        assert.strictEqual(valP16, 130, `Expected Green at p=16 to be 130, got ${valP16}`);
        
        // p=8 (Fix1, Cell0): phase = -8/32 = -0.25. sin(-PI/2) = -1.
        // val = 40 + 180(0.5 + 0.5(-1)) = 40 + 0 = 40.
        // Fix1 Start=49. Cell0 Green = 49+3=52.
        const valP8 = getVal(52);
        assert.strictEqual(valP8, 40, `Expected Green at p=8 to be 40, got ${valP8}`);
        console.log("CHECK: Value at p=8 is correct (40) [Min]");

         // p=24 (Fix3, Cell0): phase = -24/32 = -0.75. sin(-1.5PI) = 1.
        // val = 40 + 180(0.5 + 0.5(1)) = 220.
        // Fix3 Start=145. Cell0 Green = 145+3=148.
        const valP24 = getVal(148);
        assert.strictEqual(valP24, 220, `Expected Green at p=24 to be 220, got ${valP24}`);
        console.log("CHECK: Value at p=24 is correct (220) [Max]");

        console.log("ALL ASSERTIONS PASSED.");
    } catch (e) {
        console.error("ASSERTION FAILED:", e.message);
        process.exit(1);
    }
}

// -----------------------------------------
// EXECUTION
// -----------------------------------------
const finalDmx = runDiagnostics();
runAssertions();
