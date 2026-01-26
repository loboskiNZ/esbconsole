
const fs = require('fs');

// Read file content
const content = fs.readFileSync('/Volumes/WORKER/machines/x32-controller/lighting-engine.js', 'utf8');

console.log("Verifying Wash Logic (Phase 7 Spaced)...");

let fail = false;

// 1. Check Patch
const patchMatch = content.includes('washes: [232, 236, 240, 244]');
if (patchMatch) console.log("Wash Patch: CORRECT");
else {
    console.error("Wash Patch: FAILED (Expected [232, 236, 240, 244])");
    fail = true;
}

// 2. Check setWashPixel implementation
const helperRegex = /setWashPixel\(updateObj, baseAddr, r, g, b\) \{[\s\S]*?updateObj\[baseAddr \+ 0\]\s*=\s*r;[\s\S]*?updateObj\[baseAddr \+ 1\]\s*=\s*g;[\s\S]*?updateObj\[baseAddr \+ 2\]\s*=\s*b;[\s\S]*?\}/;

if (helperRegex.test(content)) {
    console.log("setWashPixel RGB Implementation: CORRECT");
} else {
    console.error("setWashPixel RGB Implementation: FAILED");
    fail = true;
}

// 3. Strict Check: NO `addr+3` writes for Washes in helper
const washHelper = content.match(/setWashPixel\([\s\S]*?\}/)[0];
if (!washHelper) {
    console.error("Could not find setWashPixel function!");
    fail = true;
} else {
    const badLines = washHelper.split('\n').filter(l => 
        (l.includes('+ 3') || l.includes('+3')) && !l.trim().startsWith('//')
    );
    if (badLines.length > 0) {
        console.error("setWashPixel has active writes to addr+3:");
        console.log(badLines.join('\n'));
        fail = true;
    } else {
        console.log("setWashPixel 4th channel writes: NONE (Correct)");
    }
}

if (fail) {
    console.error("\nXXX VERIFICATION FAILED XXX");
    process.exit(1);
} else {
    console.log("\n>>> VERIFICATION PASSED <<<");
    process.exit(0);
}
